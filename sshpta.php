<?php

class sshpta{
	function usage(){
		echo "SSHPTA v0.1\n";
	}

	function set_up_password_ssh_connection($server,$port,$username,$password){
		if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
		if(!($con = ssh2_connect($server, $port))){
		 	echo "[Error] Connection to $server : $port failed\n";
			return 0;
		}else{
    			if(!ssh2_auth_password($con, $username, $password)) {
       		 		echo "[Error] Authentication to $server : $port failed using username $username\n";
				return 0;
    			} else {
				return $con;
			}
   		}
	}


	function set_up_key_ssh_connection($server,$port,$username,$public_key,$private_key,$passphrase){
		if (!function_exists("ssh2_connect")) die("function ssh2_connect doesn't exist");
		if(!($con = ssh2_connect($server, $port,array('hostkey'=>'ssh-rsa')))){
	 		echo "[Error] Connection to $server : $port failed\n";
			return 0;
		}else{
    			if(!ssh2_auth_pubkey_file($con, $username,$public_key,$private_key, $passphrase)) {
        			echo "[Error] Authentication to $server : $port failed using public key\n";
				return 0;
    			} else {
				return $con;
			}
   		}
	}


	function ssh_shell_exec($ssh_connection, $command){
		if($ssh_connection != 0){
	        	if (!($stream = ssh2_exec($ssh_connection, $command ))) {
            			echo "[Error] Failed to execute command\n";
				return 0;
	        	} else {
       		     		stream_set_blocking($stream, true);
            			$data = "";
            			while ($buf = fread($stream,4096)) {
               		 		$data .= $buf;
            			}
            			fclose($stream);
				return $data;
			}
		}else{
			return 0;
		}
	}

}


$s = new sshpta;

$options = getopt("t:u");

if(!isset($options['t'])){
	echo "[Error] No targets file specified\n\n";
	$s->usage();
	exit();
}

$targets_file = file($options['t']);

foreach($targets_file as $targets_file_line){
	$target = explode(":",$targets_file_line);
	if(count($target) == 1){
		$host = trim($target[0]);
		$port = 22;
	}else{
		$host = trim(str_replace(":","",$target[0]));
		$port = $target[1];
	}
	echo "$host : $port\n";
}

?>
