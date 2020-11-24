<?php
/** 
 * @author    Daniel Dowse <support@mybsdadmin.de>
 * @copyright 2020 Daniel Dowse
 * @version   0.2
 * @link      https://github.com/ddowse/pf-tunnelactive
 * THE SOFTWARE IS PROVIDED AS IS, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, 
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A 
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT 
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION 
 * OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 **/


require_once "openvpn.inc";
require_once "service-utils.inc";

//Send notification to webgui/webconfigurator
function Send_msg($notice)
{
    file_notice(
        $id, $notice, $category = "OpenVPN", $url = "", 
        $priority = 1, $local_only = true
    );
} 

// Checking status 
function Check($clients)
{
    foreach($clients as $check) {
        $status = $check['status'];
        if (strcmp($status, "up") != 0) { 
            return "true"; 
        } 
    }
}    

while(true) {
        
    echo "Sleeping (" . $argv[2] . ")..." ;
    
    // What ovpn clients are marked as active        
    $clients = openvpn_get_active_clients();
        
    if (Check($clients)) {  
                    
           echo "\nReconnecting...\n";    
        
        //loop over ...
        foreach ($clients as $client) {        
               $extras['vpnmode'] = "client";
               $extras['id'] = $client['vpnid'];
        //..and disconnect
               service_control_stop("openvpn", $extras);
               echo "Stopping: " .  $client['name'];
        
            for ($i=0;$i<3;$i++) {
                sleep(1);
                echo " * ";
            }
                       echo "\n";
        }
        
        //how many ovpn clients?
        $index=count($clients);
        
        //reverse the array
        foreach (array_reverse($clients) as $client) {
            //take one off
            $index--;
            echo "Starting:" . $client['name'];
            $extras['vpnmode'] = "client";
            $extras['id'] = $client['vpnid'];
            
            //Start with the most outer tunnel
            service_control_start("openvpn", $extras);
            
            for ($i=0;$i<$argv[1];$i++) {
                   sleep(1);
                   echo " * ";
                   // Reread the Status!
                   $update = openvpn_get_active_clients();
                   $status = $update[$index]['status'];
        
                if (strcmp($status, "up") == 0) { 
                    echo "OK\n";
                    $i = $argv[1];
                }  
            } 
        
            if (strcmp($status, "up") == 0) { 
            } else {
                reset($clients);
                echo "failed.\n";
                break;
            }    
        }
            Send_msg("OpenVPN Tunnels restarted");    
             
    } else {
        for ($i=0;$i<$argv[2];$i++) {
               sleep(1);
               echo " * ";
        } 
        echo "Checking\n";
    }
}

