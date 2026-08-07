<?php
/*
 * QRCode encoder by Scott A. Dixon
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * Based on PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010-2013 Dominik Dzienia <deltalab at poczta dot fm>
 */
	
$QR_BASEDIR = dirname(__FILE__) . DIRECTORY_SEPARATOR;

// required libs

$libs = [
	'qrconst',
	'qrconfig',
	'qrtools',
	'qrspec',
	'qrimage',
	'qrinput',
	'qrbitstream',
	'qrsplit',
	'qrrscode',
	'qrmask',
	'qrencode',
	'qrarea',
	'qrcanvas',
	'qrsvg'
];

foreach ($libs as $l) {
	include $QR_BASEDIR . $l . '.php';
}

// eof