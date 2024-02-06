#!/usr/bin/env php

<?php

// Open the standard streams
$stdout = fopen("php://stdout", "w");
$stderr = fopen("php://stderr", "w");
$stdin = fopen("php://stdin", "r");

// Write a nice banner
fwrite($stdout, 
    "Composer Fetcher Script" . PHP_EOL
    . "By MXPSQL" . PHP_EOL . PHP_EOL
);

{ // Check for CLI
    if(php_sapi_name() != "cli"){
	echo "Please run this script under a Shell/CLI." . PHP_EOL;
        exit(1);
    }
}

// Handle args
$force = false; // -f
$tmpdir = "."; // -y
$whichphp = "php"; // -p
$composerrun = array();
{ // Parse args by hand
    for($i = 1; $i < $argc; $i++){
        $args = $argv[$i];
        $nextargs = null;
        if($i+1 < $argc){
            $nextargs = $argv[$i+1];
        }

        if($args == "-f"){
            $force = true;
        }
        else if($args == "-y"){
            if($nextargs === null){
                fwrite($stderr, "Missing args to " . $args . PHP_EOL);
                exit(1);
            }
            $tmpdir = $nextargs;
            $i++;
        }
        else if($args == "-p"){
            if($nextargs === null){
                fwrite($stderr, "Missing args to " . $args . PHP_EOL);
                exit(1);
            }
            $whichphp = $nextargs;
            $i++;
        }
        else if($args == "-h"){
            fwrite($stdout, $argv[0] . " Usage: " . PHP_EOL
                . "-f - Force to run the installer in the presence of a bad checksum." . PHP_EOL
                . "-y - Set the directory to download the installer to." . PHP_EOL
                . "-p - Set for which php to execute the installer." . PHP_EOL
                . "-h - Show this help message." . PHP_EOL
                . PHP_EOL 
            );
            exit(0);
        }
        else{
            array_push($composerrun, $args);
        }
    }
}

// Fetch some files from composer.
fwrite($stdout, "Fetching the checksums and installers..." . PHP_EOL);
$expected_cksum = file_get_contents("https://composer.github.io/installer.sig");
$installer = file_get_contents("https://getcomposer.org/installer");

if(($expected_cksum === false) || ($installer === false)){
    fwrite($stderr, "Failed to download either the checksum or the installer." . PHP_EOL);
    exit(1);
}

// Compare the checksums
if(!$force){ fwrite($stdout, "Comparing checksum" . PHP_EOL);
    $installer_cksum = hash("sha384", $installer);
    if($installer_cksum !== $expected_cksum){
        fwrite($stderr, 
         "Installer corrupt!" . PHP_EOL
         . "Expected => " . $expected_cksum . PHP_EOL
         . "Got => " . $installer_cksum
         . PHP_EOL);
        exit(1);
    }
}

// Write the installer
$installer_path = null;
{ fwrite($stdout, "Writing installer..." . PHP_EOL);
    $installer_path = tempnam($tmpdir, "fcp");
    if($installer_path === false){
        fwrite($stderr, "Failed to get temporary file to write installer to." . PHP_EOL);
        exit(1);
    }

    $fpcstatus = file_put_contents($installer_path, $installer);
    if($fpcstatus === false || $fpcstatus < strlen($installer)){
        fwrite($stderr, "Failed to write installer." . PHP_EOL);
        exit(1);
    }

    $installer_path = realpath($installer_path);
    if($installer_path === false){
        fwrite($stderr, "Failed to get real path to installer." . PHP_EOL);
        exit(1);
    }
}

// Run the installer
$exitcode = 0;
{
    fwrite($stdout, "Executing installer..." . PHP_EOL);
    $output = array();
    $status = exec($whichphp . " " . $installer_path . " --quiet", $output);

    fwrite($stdout, "Finished execution of installer." . PHP_EOL);
    fwrite($stdout, PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL . PHP_EOL);
    fwrite($stdout, implode(PHP_EOL, $output) . PHP_EOL . PHP_EOL);

    $unlinkstatus = unlink($installer_path);
    if($status === false){
        fwrite($stderr, "Failed to delete installer. Remove it yourself." . PHP_EOL
         . "Installer => " . $installer_path . PHP_EOL
        );
    }

    if($status === false){
        fwrite($stderr, "Installer returned failure." . PHP_EOL);
        exit($exitcode);
    }
}

fwrite($stdout, "Finished fetching and installing composer." . PHP_EOL);
exit($exitcode);

?>
