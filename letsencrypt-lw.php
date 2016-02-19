#!/usr/bin/php-cli
<?php
include("Console/Getopt.php");

function get_options() {	
	$cg = new Console_Getopt;
	$args = $cg->readPHPArgv();
	array_shift($args);
	$shortopts = '';
	$longopts = ["domain=", "email=", "user=", "webroot-subdir=", "dry-run", "verbose", "letsencrypt=", "debug", "cron"];
	$options = $cg->getopt2($args, $shortopts, $longopts);
	if(PEAR::isError($options)) {
		print 'Error: '.$options->getMessage()."\n";
		exit(1);
	}
	$result = ['domains' => [], 'dry-run' => false, 'webroot-subdir' => 'public_html',  'verbose' => false,
			'letsencrypt' => '/root/letsencrypt/letsencrypt-auto', 'debug' => false, 'cron' => false,
	];
	foreach($options[0] as $opt) {
		switch($opt[0]) {
			case "--email":
				$result['email'] = $opt[1];
				break;
			case "--user":
				$result['user'] = $opt[1];
				break;
			case "--domain":
				$result['domains'][] = $opt[1];
				break;
			case "--webroot-subdir":
				$result['webroot-subdir'] = $opt[1];
				break;
			case "--debug":
				$result['debug'] = true;
				break;
			case "--cron":
				$result['cron'] = true;
				break;
			case "--verbose":
				$result['verbose'] = true;
				break;
			case "--dry-run":
				$result['dry-run'] = true;
				break;
				case "--letsencrypt":
				$result['letsencrypt'] = $opt[1];
				break;
		}
	}
	return $result;
}

function my_shell_exec($cmd, &$stdout=null, &$stderr=null) {
	$proc = proc_open($cmd,[
			1 => ['pipe','w'],
			2 => ['pipe','w'],
	],$pipes);
	$stdout = stream_get_contents($pipes[1]);
	fclose($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[2]);
	return proc_close($proc);
}

function my_print($str) {
	global $output, $options;
	if($options['debug']) {
		print($str);
	}
	$output .= $str;
}

function my_exit() {
	global $exit_code, $output, $options;
	if($options['cron']) {
		if($exit_code) {
			print $output;
		}
	} else {
		print $output;
	}
	exit($exit_code);
}

$exit_code = 0;
$output = "";
$options = get_options();

# Location of the letsencrypt script
$le = $options['letsencrypt'];

$domain = $options['domains'][0]; // The primary domain; how the cert will be named
$domains = join(' ', $options['domains']);
$domain_args = join(' -d ', $options['domains']);
$username = $options['user'];
$webroot_subdir = $options['webroot-subdir'];
$email = $options['email'];
my_print("Retrieving the SSL certificates for the domains $domains...\n");

$cmd = "$le --text --agree-tos --email $email certonly --renew-by-default --webroot --webroot-path /home/$username/$webroot_subdir/ -d $domain_args";
my_print("The command is: $cmd");

$le_code = 0;
if($options['dry-run']) {
	$result = "Not run";
} else {
	$le_code = my_shell_exec($cmd, $le_stdout, $le_stderr);
	$result = "**stdout:$le_stdout\n**stderr:$le_stderr";
}
my_print("\nCommand completed: \n$result\n");
if($le_code == 0) {
	my_print("Setting up certificates for the domain\n");
	$whmusername = 'root';
	$hash = file_get_contents('/root/.accesshash');
	$query = "https://127.0.0.1:2087/json-api/listaccts?api.version=1&search=$username&searchtype=user";
	$curl = curl_init();
	if(!$curl) {
		my_print("curl_init() failed");
		$exit_code = 101;
		my_exit();
	}
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST,0);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER,0);
	curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
	
	$header[0] = "Authorization: WHM $whmusername:" . preg_replace("'(\r|\n)'","",$hash);
	curl_setopt($curl,CURLOPT_HTTPHEADER,$header);
	curl_setopt($curl, CURLOPT_URL, $query);
	$ip = curl_exec($curl);
	if ($ip == false) {
		my_print("Curl error: " . curl_error($curl));
		$exit_code = 100;
		my_exit();
	}
	$ip = json_decode($ip, true);
	$ip = $ip['data']['acct']['0']['ip'];
	my_print("IP: $ip\n");
	
	$cert = urlencode(file_get_contents("/etc/letsencrypt/live/" . $domain . "/cert.pem"));
	$key = urlencode(file_get_contents("/etc/letsencrypt/live/" . $domain . "/privkey.pem"));
	$chain = urlencode(file_get_contents("/etc/letsencrypt/live/" . $domain . "/chain.pem"));
	
	$query = "https://127.0.0.1:2087/json-api/installssl?api.version=1&domain=$domain&crt=$cert&key=$key&cab=$chain&ip=$ip";
	curl_setopt($curl, CURLOPT_URL, $query);
	$result = curl_exec($curl);
	if ($result == false) {
		my_print("Curl error: " . curl_error($curl));
		$exit_code = 200;
		my_exit();
	}
	curl_close($curl);
	my_print($result);
}

my_print("All Done\n");
my_exit();
