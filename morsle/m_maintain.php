<?php
require_once('../../config.php');
require_once("$CFG->dirroot/repository/morsle/lib.php");
require_once("$CFG->dirroot/google/lib.php");
$str = strtolower('65259-paid-111d-02');
$owner = 'puffro01@luther.edu';
//$owner = $str . '@luther.edu';
$title = $str . '-read';
$morsle = new repository_morsle();
//$morsle->get_token('drive');
$status = $morsle->m_maintain($str);
//$status = get_collection($title, $owner, $morsle);
/*
$file = "$CFG->dirroot/local/admissions/katie_deposited.csv";
$filetype = mimeinfo('type', "$CFG->dirroot/local/admissions/katie_deposited.csv");
$collectionid = '0B9-LjN6v5M_DMmFhZTY5YTUtODAzOS00ZDA3LWI4OGMtYTY4MDQ5ZjhjMWU4';
$success = send_file_togoogle($morsle, 'katie_deposited.csv', $file, $filetype, $collectionid);
 * 
 */