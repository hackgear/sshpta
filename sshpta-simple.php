<?php

class sshpta{
	function usage(){
		echo "SSHPTA (simple) v0.1\n";
		echo "Older version of tool with only basic features\nUsage:\n\n";
		echo "-t\tTarget List File or Target\n";
		echo "-u\tUser List File or User\n";
		echo "-p\tPassword List File or Password\n";
		echo "-c\tCommand List File or Command\n";
		echo "-l\tEnable Logging to this directory\n";
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

$options = getopt("t:u:p:c:l:b");

if(!isset($options['t'])){
	echo "[Error] No target list (or target) specified\n\n";
	$s->usage();
	exit();
}
if(!isset($options['u'])){
	echo "[Error] No user list (or user) specified\n\n";
	$s->usage();
	exit();
}
if(!isset($options['c'])){
	echo "[Error] No command list (or command) specified\n\n";
	$s->usage();
	exit();
}

if(file_exists($options['t'])){
	$targets_file = file($options['t']);
}else{
	$targets_file = array();
	echo "[Info] Target file does not not exist\n";
	echo "[Info] Using single target '".trim($options['t'])."'\n";
	$targets_file[] = trim($options['t']);
}

if(file_exists($options['c'])){
	$commands_file = file($options['c']);
}else{
	$commands_file = array();
	echo "[Info] Command list file does not not exist\n";
	echo "[Info] Added single command '".trim($options['c'])."'\n";
	$commands_file[] = trim($options['c']);
}

foreach($targets_file as $targets_file_line){
	// Arrays Associated with Authenticating
	$users = array();
	$passwords = array();
	$private_keys = array();
	$public_keys = array();
	$passphrases = array();

	// Arrays Associated with Automation
	$command_list = array();
	$local_bash_script = array();


	$target = explode(":",$targets_file_line);
	if(count($target) == 1){
		$host = trim($target[0]);
		$port = 22;
	}elseif (count($target) == 2){
		$host = trim(str_replace(":","",$target[0]));
		$port = $target[1];
	}else{
		$host = trim(str_replace(":","",$target[0]));
		$port = trim(str_replace(":","",$target[1]));
		$password = trim($target[2]);
		$passwords[] = $password;
	}

	echo "[Target]  $host:$port\n";

	if(file_exists($options['u'])){
		$users_file = file($options['u']);
	}else{
		$users_file = array();
		echo "[Info] Users file does not not exist\n";
		echo "[Info] Using single user '".trim($options['u'])."'\n";
		$users_file[] = trim($options['u']);
	}

	foreach($users_file as $users_file_line){
		$users[] = trim($users_file_line);
	}

	if(count($users) > 0){
		foreach($users as $user){
			echo "[Info] User = $user\n";

			if(isset($options['p'])){
				if(file_exists($options['p'])){
					$passwords_file = file($options['p']);
				}else{
					$passwords_file = array();
					$passwords_file[] = trim($options['p']);
				}
				foreach($passwords_file as $passwords_file_line){
					$passwords[] = trim($passwords_file_line);
				}
			}

			if(count($passwords) > 0){
				foreach($passwords as $password){
					$connection = $s->set_up_password_ssh_connection($host,$port,$user,$password);
					if($connection != 0){
						echo "[Success] We have a connection to $host\n";
						echo "[Info] Remote Hostname: ".$s->ssh_shell_exec($connection,"uname -n")."\n";
						$shell = ssh2_shell($connection, 'vt102', null, 80, 40, SSH2_TERM_UNIT_CHARS);
						$shell_log = '';
						stream_set_blocking($shell,false);
						sleep(1);
						$data = "";
            					while ($buf = fread($shell,4096)) {
                					$data .= $buf;
						}

						foreach($commands_file as $commands_file_line){
							$commands_file_line = str_replace("{{sshpta-username}}",$user,$commands_file_line);
							$commands_file_line = str_replace("{{sshpta-password}}",$password,$commands_file_line);
							$command_list[] = trim($commands_file_line);
						}

						foreach($command_list as $command){
							$hash = md5(microtime());
							fwrite($shell, $command."; echo $hash\n");
							sleep(1);
							$matches = array();
							while(true){
	            						$shell_log .= fread($shell,4096);
								if(substr_count($shell_log,$hash) == 2){
									break;
								}
							}
						}
						echo $shell_log."\n";
						if(isset($options['l'])){
							$log_directory = trim($options['l']);
							echo "[Info] Log directory is '$log_directory'\n";
							$log_file = $log_directory.time()."_".$host.".txt";
							echo "[Log] Saving shell output to '$log_file'\n";
							file_put_contents($log_file,$shell_log);
						}
						fclose($shell);
						echo "\n===\n";
					}
				}
			}
		}
	}else{
		echo "[Info] Skipping this host, no users\n";
	}
}

?>
