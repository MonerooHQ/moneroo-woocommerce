<?php

$projectPath = __DIR__;
//Declare directories which contains php code
$scanDirectories = [
    $projectPath . '/admin/',
    $projectPath . '/includes/',
];
//Optionally declare standalone files
$scanFiles = [
    $projectPath . '/moneroo-for-woocommerce.php',
];

return [
    'composerJsonPath' => $projectPath . '/composer.json',
    'vendorPath'       => $projectPath . '/vendor/',

    'skipPackages' => [],

    'scanDirectories' => $scanDirectories,
    'scanFiles'       => $scanFiles,
];
