<?php
//----------------------------------------------------------
//---- Author: Dirk van der Walt
//---- License: GPL v3
//---- Description: 
//---- Date: 29-05-2013
//------------------------------------------------------------

App::uses('Component', 'Controller');

class KickerComponent extends Component {


    var $radclient;

    function kick($radacct_entry){

        //---Location of radclient----
        $this->radclient = Configure::read('freeradius.radclient');

        //Check if there is a NAS with this IP
        $nas_ip             = $radacct_entry['nasipaddress'];
        $username           = $radacct_entry['username'];
        $nasportid          = $radacct_entry['nasportid'];
        $framedipaddress    = $radacct_entry['framedipaddress'];
		$device_mac			= $radacct_entry['callingstationid'];
        $nas_mac			= $radacct_entry['calledstationid'];

        $q_r                = ClassRegistry::init('Na')->findByNasname($nas_ip);

        if($q_r){

            //Check the type
            $type = $q_r['Na']['type'];
            //======================================================================================
            //=======Different Types of NAS devices Require different type of disconnect actions====
            //======================================================================================
            if(($type == 'CoovaChilli-AP')|($type == 'CoovaChilli')){

                //Check the port of the device's COA
                $port   = $q_r['Na']['ports'];
                $secret = $q_r['Na']['secret'];

                //Send the NAS a POD packet
                //-------------------------------------------
                if($nas_ip == '0.0.0.0'){   //This is a hack for Chillispot since it reports 0.0.0.0
                    $nas_ip='127.0.0.1';
                }
                //Now we can attempt to disconnect the person
                $output = array();
                //Get the location of the radpod script
                // print("Disconnecting $username");
                //You may need to fine-tune the -t and -r switches - See man radclient for more detail
                $rc = $this->radclient;

                //Just send both to the device to be sure...
			    exec("echo \"User-Name = $device_mac\"  | $rc -r 2 -t 2 $nas_ip:$port 40 $secret",$output);
                exec("echo \"User-Name = $username\"    | $rc -r 2 -t 2 $nas_ip:$port 40 $secret",$output);
                //----------------------------------------------
            }

             //____ Mikrotik _____ 
    		if($type == 'Mikrotik'){
        		$port   = $q_r['Na']['ports'];
        		$secret = $q_r['Na']['secret'];
        		//Mikrotik requires that we need to know the IP the user comes in with
        		$rc = $this->radclient;

				//If it is a MAC authenticated device - also send a disconnect command using the device MAC as username
				if($device_flag >= 1){
					exec("echo \"Framed-IP-Address=$framedipaddress,User-Name=$device_mac\" | $rc -r 2 -t 2 $nas_ip:$port disconnect $secret",$output);
				} 
        		exec("echo \"Framed-IP-Address=$framedipaddress,User-Name=$username\" | $rc -r 2 -t 2 $nas_ip:$port disconnect $secret",$output);
    		}

            //==========================================================================================
        }
    }

}

?>
