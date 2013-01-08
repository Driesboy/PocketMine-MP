<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

function UPnP_PortForward($port){
	if(Utils::getOS() != "win" or !class_exists("COM")){
		return false;
	}
	$port = (int) $port;
	$myLocalIP = gethostbyname(trim(`hostname`));
	try{
		$com = new COM("HNetCfg.NATUPnP");
		if($com === false or !is_object($com->StaticPortMappingCollection)){
			return false;
		}
		$com->StaticPortMappingCollection->Add($port, "UDP", $port, $myLocalIP, true, "PocketMine-MP");
	}catch(Exception $e){
		return false;
	}
	return true;
}

function UPnP_RemovePortForward($port){
	if(Utils::getOS() != "win" or !class_exists("COM")){
		return false;
	}
	$port = (int) $port;
	try{
		$com = new COM("HNetCfg.NATUPnP") or false;
		if($com === false or !is_object($com->StaticPortMappingCollection)){
			return false;
		}
		$com->StaticPortMappingCollection->Remove($port, "UDP");
	}catch(Exception $e){
		return false;
	}
	return true;
}