# pf-tunnelactive
Check and restart OpenVPN Client(s) and Cascade Setup

This PHP Code uses the native php functions of pfsense to check the status of an OpenVPN client connection.  
The intention and main purpose is to keep a 3 tunnel cascade setup alive and restart all tunnels in the  
correct order in the event of a disconnect.  

#Installation

Download the code from github.

#Usage
The Script can be run via shell(ssh) and send into the background like this

```
[2.5.0-DEVELOPMENT][root@pfSense.localdomain]/root: nohup /root/pf-tunnelactive/tunnelactive.php 10
```

or use pfsense **Command Prompt** found in **Diagnostics**. (Untested!)

The Programm takes 1 argument. The time in seconds to wait for the connection status to change to "Up". 

In the example above it's 10 seconds. 

To stop the exection, you have to terminate to program.

```
[2.5.0-DEVELOPMENT][root@pfSense.localdomain]/root: pkill -f tunnelactive.php
```

#Examples

If 1 VPN connection can't be established the programme will loop forever on this tunnel. 
You should then check the OpenVPN Logs for further details. 

```
Starting:PP_Oslo UDP4 *  *  *  *  *  *  *  *  *  * failed.
Stopping: PP_Amsterdam UDP4 *  *  * 
Stopping: PP_Berlin UDP4 *  *  * 
Stopping: PP_Oslo UDP4 *  *  * 

Starting:PP_Oslo UDP4 *  *  *  *  *  *  *  *  *  * failed.
Stopping: PP_Amsterdam UDP4 *  *  * 
Stopping: PP_Berlin UDP4 *  *  * 
Stopping: PP_Oslo UDP4 *  *  * 

Starting:PP_Oslo UDP4 *  *  *  *  *  *  *  *  *  * failed.
```

When everything with your OpenVPn Connection are good along with all other Network Settings on the pfsense.
The console output would look like this on a restart of all tunnels.

[Truncated Output]
```
Stopping: PP_Amsterdam UDP4 *  *  * 
Stopping: PP_Berlin UDP4 *  *  * 
Stopping: PP_Oslo UDP4 *  *  * 

Starting:PP_Oslo UDP4 *  *  * up

SUCCESS

Starting:PP_Berlin UDP4 *  * up

SUCCESS

Starting:PP_Amsterdam UDP4 *  *  * up

SUCCESS
Nothing to do Hit Ctrl+c to stop
 - 5 Second Pause
Nothing to do Hit Ctrl+c to stop
 - 5 Second Pause
```

As you can see the script will first take alle tunnels down with 

```php
service_control_stop("openvpn", $extras);
```

that are returned by this function 

```php
openvpn_get_active_clients();
```
as an array of objects.

 ```json
 Array
(
    [0] => Array
        (
            [port] => 
            [name] => PP_Amsterdam UDP4
            [vpnid] => 1
            [mgmt] => client1
            [status] => down
        )

    [1] => Array
        (
            [port] => 
            [name] => PP_Berlin UDP4
            [vpnid] => 2
            [mgmt] => client2
            [status] => down
        )

    [2] => Array
        (
            [port] => 
            [name] => PP_Oslo UDP4
            [vpnid] => 3
            [mgmt] => client3
            [status] => down
        )

)
```
Then started back up again with

```php
service_control_start("openvpn", $extras);
```

reverse way. As we want to create a cascade of tunnels.

#Cascading 

*Important* We assume that you have checked that your OpenVPN Configuration is in general functional and
that your VPN Provider Supports this. *Important*

The Numbering is vpnid. 

The 1st (vpnid 3) VPN Tunnel  will be used to connect the the endpoint of the 2nd Tunnel.
The 2nd (vpnid 2) VPN Tunnel to will be used to connect to the endpoint of the 3rd Tunnel.
The 3rd (vpnid 1) VPN Tunnel is the exit to the internet. 

We check the box 

[x] Don't add/remove routes - (Don't add or remove routes automatically)

and add under **Advanced Configuration** / **Custom options** In the configuration of VPNID 3 
and VPNID 2. 

```
up "/root/pf-cascade/addroute.sh Upper_Tunnel_IP"
```
Replace Upper_Endpoint_IP with the IP of the next VPN Endpoint you want to connect to.

VPNID1 leave the routing to default as we want a the route to be set.
Start the script from this repo and check the routing tabke either with the webinterface or with
the shell.

```
[2.5.0-DEVELOPMENT][root@pfSense.localdomain]/root: netstat -4nr
Internet:
Destination        Gateway            Flags     Netif Expire
0.0.0.0/1          10.3.1.2           UGS      ovpnc1
default            192.168.1.1        UGS      vtnet0
10.1.117.0/24      10.1.117.1         UGS      ovpnc3
10.1.117.1         link#8             UH       ovpnc3
10.1.117.19        link#8             UHS         lo0
10.3.1.0/24        10.3.1.1           UGS      ovpnc1
10.3.1.1           link#6             UH       ovpnc1
10.3.1.22          link#6             UHS         lo0
10.4.167.0/24      10.4.167.1         UGS      ovpnc2
10.4.167.1         link#7             UH       ovpnc2
10.4.167.250       link#7             UHS         lo0
80.255.7.98/32     10.1.117.1         UGS      ovpnc3
85.17.28.145/32    10.4.167.1         UGS      ovpnc2
127.0.0.1          link#3             UH          lo0
128.0.0.0/1        10.3.1.2           UGS      ovpnc1
192.168.1.0/24     link#1             U        vtnet0
192.168.1.1        00:a0:98:41:88:4e  UHS      vtnet0
192.168.1.128      link#1             UHS         lo0
```

As you can see the routing is set to route the VPN Server IP's from 

ovpnc3 => (80.255.7.98) ovpnc2 => (85.17.28.145) ovpnc1 => (0.0.0.0/1 ) Internet
