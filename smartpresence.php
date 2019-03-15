<?php
   $xml = "<?xml version=\"1.0\" encoding=\"ISO-8859-1\" ?>";      
   //***********************************************************************************************************************
   // V0.3 : smartPresence- Simulation automatique de présence / Influman 2019
   
	// recuperation des infos depuis la requete
    $action = getArg("action", true, ''); // setmode ou getstatus
	$lights = getArg("lights", false, '');
	$shutters = getArg("shutters", false, '');
	$score= getArg("score", false, '');
	$value = getArg("value", false, '');
	$apiu = getArg("apiu", false, '');
	$apis = getArg("apis", false, '');
	$debug = getArg("debug", false, '');
	$zone = getArg("zone", false, '');
    // API DU PERIPHERIQUE APPELANT LE SCRIPT
    $periph_id = getArg('eedomus_controller_module_id'); 
	
	if ($action == '' ) {
		die();
	}
	
	//////////////////////////
	$majmanuelle = false;
	if ($debug == "update") {
		$majmanuelle = true;
	}
	//////////////////////////
	// Lecture Lights
	$tab_lights = array();
	$lightoff = 0;
	$lighton = 100;
	$i = 0;
	$j = 0;
    $tab_param = explode(",",$lights);
	if (is_numeric($tab_param[0])) {
		$lightoff = $tab_param[0];
		while ($i < count($tab_param)) {
			$i++;
			if ($i == 1 && is_numeric($tab_param[$i])) {
				$lighton = $tab_param[$i];
				
			} else {
				if (is_numeric($tab_param[$i])) {
					$tab_lights[$j] = $tab_param[$i];
					if ($j == 0) {
						$tab_valuelist = getPeriphValueList($tab_lights[$j]);
						foreach($tab_valuelist As $value_list) {
							if ($value_list["value"] == $lighton) {
								$lightondesc = $value_list["state"]; 
								break;
							}
						}
					}
					$j++;
				}
			}
		}
	}
	
	// Lecture Shutters
	$tab_shutters = array();
	$shutterclosed = 0;
	$shutteropened = 100;
	$i = 0;
	$j = 0;
    $tab_param = explode(",",$shutters);
	if (is_numeric($tab_param[0])) {
		$shutterclosed = $tab_param[0];
		while ($i < count($tab_param)) {
			$i++;
			if ($i == 1 && is_numeric($tab_param[$i])) {
				$shutteropened = $tab_param[$i];
				
			} else {
				if (is_numeric($tab_param[$i])) {
					$tab_shutters[$j] = $tab_param[$i];
					if ($j == 0) {
						$tab_valuelist = getPeriphValueList($tab_shutters[$j]);
						foreach($tab_valuelist As $value_list) {
							if ($value_list["value"] == $shutteropened) {
								$shutteropeneddesc = $value_list["state"]; 
								break;
							}
						}
					}
					$j++;
				}
			}
		}
	}
	
	//////////////////////////
	
	if ($action == 'setmode') {
			// Si Mode Auto, test si Score existant
			if ($value == "auto") {
				if (is_numeric($score)) {
					saveVariable('SMARTPRESENCE_SCORE_ID_'.$zone, $score);
					saveVariable('SMARTPRESENCE_MODE_'.$zone, 'AUTO');
					die();
				} else {
					// pas de score, mode auto impossible, retour à inactif
					saveVariable('SMARTPRESENCE_SCORE_ID_'.$zone, '');
					saveVariable('SMARTPRESENCE_MODE_'.$zone, 'STOP');
					setValue($periph_id, 0);
					die();
				}
			}
			if ($value == "stop") {
				saveVariable('SMARTPRESENCE_MODE_'.$zone, 'STOP');
				die();
			}
			if ($value == "onall") {
				saveVariable('SMARTPRESENCE_MODE_'.$zone, 'ONALL');
				die();
			}
			if ($value == "onlight") {
				saveVariable('SMARTPRESENCE_MODE_'.$zone, 'ONLIGHT');
				die();
			}
			if ($value == "onshutter") {
				saveVariable('SMARTPRESENCE_MODE_'.$zone, 'ONSHUTTER');
				die();
			}
		die();
	}
	
	//////////////////////////////////
	
	
	if ($action == 'getstatus') {
		$jour = date('N');
		$jourdumois = date('j');
		$min = date('i');
		if ($min < 30) {
			$min = "00";
		} else {
			$min = "30";
		}
		$heure = date("H").":".$min;
		
		// recuperation du mode
		$mode = "STOP";
		$preload = loadVariable('SMARTPRESENCE_MODE_'.$zone);
		if ($preload != '' && substr($preload,0,8) != "## ERROR") {
			$mode = $preload;
		} 
		$status = $mode;
		// recherche du score de présence actuel
		if ($mode == "AUTO") {
			$actualscore = 1; // Présence
			$preload = loadVariable('SMARTPRESENCE_SCORE_ID_'.$zone);
			if ($preload != '' && substr($preload,0,8) != "## ERROR") {
				$actualscore_api = $preload;
				$actualscore_tab = getValue($actualscore_api);
				$actualscore = $actualscore_tab['value'];
				if ($actualscore <= -18) { // absence prolongée
					$mode = "ONAUTO";
					$status = $status."(".$actualscore.")";
				}
			}	
		}
		$preload = loadVariable('SMARTPRESENCE_LIGHTS_'.$zone);
		if ($preload != '' && substr($preload,0,8) != "## ERROR") {
			$tab_valuelights = $preload;
		} else {
			$tab_valuelights = array();
		}
		$preload = loadVariable('SMARTPRESENCE_SHUTTERS_'.$zone);
		if ($preload != '' && substr($preload,0,8) != "## ERROR") {
			$tab_valueshutters = $preload;
		} else {
			$tab_valueshutters = array();
		}
		// SmartPresence Actif
		$actif = false;
		if ($mode == "ONAUTO" || $mode == "ONALL" || $mode == "ONLIGHT" || $mode == "ONSHUTTER") {
			$actif = true;
			// gestion des lumières
			if ($mode == "ONAUTO" || $mode == "ONALL" || $mode == "ONLIGHT") {
				
				// recherche de la valeur de créneau dans le tableau
				$i = 0;
				$nblighton = 0;
				while ($i < count($tab_lights)) {
					$light_id = $tab_lights[$i];
					if (array_key_exists($light_id, $tab_valuelights)) {
						if (array_key_exists($jour, $tab_valuelights[$light_id])) {
							if (array_key_exists($heure, $tab_valuelights[$light_id][$jour])) {
								if ($tab_valuelights[$light_id][$jour][$heure] > 0) { 
									setValue($light_id, $lighton);
										$nblighton++;
								}
								if ($tab_valuelights[$light_id][$jour][$heure] < 0) {
									setValue($light_id, $lightoff);
								}
							}
						}
					}
					$i++;
				}	
			}
			// gestion des volets
			if ($mode == "ONAUTO" || $mode == "ONALL" || $mode == "ONSHUTTER") {
				
				// recherche de la valeur de créneau dans le tableau
				$i = 0;
				$nbshutteron = 0;
				while ($i < count($tab_shutters)) {
					$shutter_id = $tab_shutters[$i];
					if (array_key_exists($shutter_id, $tab_valueshutters)) {
						if (array_key_exists($jour, $tab_valueshutters[$shutter_id])) {
							if (array_key_exists($heure, $tab_valueshutters[$shutter_id][$jour])) {
								if ($tab_valueshutters[$shutter_id][$jour][$heure] > 0) { 
									setValue($shutter_id, $shutteropened);
									$nbshutteron++;
								} 
								if ($tab_valueshutters[$shutter_id][$jour][$heure] < 0) { 
									setValue($shutter_id, $shutterclosed);
								}
							}
						}
					}
					$i++;
				}	
			}
			// status
			$status .= " | ".$nblighton." | ".$nbshutteron; 
		}
		// SmartPresence Inactif, mise à jour quotidienne après 23h30
		if (!$actif) {
			// récupération date de dernière mise à jour
			$lastupdate = 0;
			$preload = loadVariable('SMARTPRESENCE_LASTUPDATE_'.$zone);
			if ($preload != '' && substr($preload,0,8) != "## ERROR") {
				$lastupdate = $preload;
			}
			// si date de derniere mise à jour <> date du jour, et si heure supérieure à 23h30, alors mise à jour pour la journée en cours
			if (($lastupdate != $jourdumois && date('G') == 23 && date('i') > 30) || $majmanuelle) {
				saveVariable('SMARTPRESENCE_LASTUPDATE_'.$zone, $jourdumois);
				// récupération historique des lumières du jour
				$tab_creneau = array("00:00" => 0, "00:30" => 0, "01:00" => 0, "01:30" => 0, "02:00" => 0, "02:30" => 0, "03:00" => 0, "03:30" => 0,
								 "04:00" => 0, "04:30" => 0, "05:00" => 0, "05:30" => 0, "06:00" => 0, "06:30" => 0, "07:00" => 0, "07:30" => 0,
								 "08:00" => 0, "08:30" => 0, "09:00" => 0, "09:30" => 0, "10:00" => 0, "10:30" => 0, "11:00" => 0, "11:30" => 0,
								 "12:00" => 0, "12:30" => 0, "13:00" => 0, "13:30" => 0, "14:00" => 0, "14:30" => 0, "15:00" => 0, "15:30" => 0,
								 "16:00" => 0, "16:30" => 0, "17:00" => 0, "17:30" => 0, "18:00" => 0, "18:30" => 0, "19:00" => 0, "19:30" => 0,
								 "20:00" => 0, "20:30" => 0, "21:00" => 0, "21:30" => 0, "22:00" => 0, "22:30" => 0, "23:00" => 0, "23:30" => 0);
							
				$paramDate = "&start_date=".str_replace(" ","%20",date("Y-m-d H:i:s", mktime(0, 0, 0, date("m") , date("d"), date("Y"))));
				$nbmaj = 0;
				$nblignemaj = 0;
				foreach ($tab_lights as $light_id) {
					if (!array_key_exists($light_id, $tab_valuelights)) {
						$tab_valuelights[$light_id] = array();
					}
					if (!array_key_exists($jour, $tab_valuelights[$light_id])) {
						$tab_valuelights[$light_id][$jour] = $tab_creneau;
					}
					$arrHistorique = array();
					$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$light_id."&api_user=".$apiu."&api_secret=".$apis.$paramDate;
					$result = httpQuery($urlHistorique, 'GET');
					$arrHistorique = sdk_json_decode(utf8_encode($result));
					if (array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique["body"])) {
						$listeHistoriques = $arrHistorique["body"]["history"];
					}
					$url = $urlHistorique;
					if(isset($listeHistoriques)) {
						$tab_valuelights[$light_id][$jour] = $tab_creneau;
						$nbmaj++;
						// Parcours de l'historique
						foreach ($listeHistoriques as $historique) {
							$nblignemaj ++;
							$dateHisto = $historique[1];
							$heureHisto = substr($dateHisto, 11, 2);
							$minHisto = substr($dateHisto, 14, 2);
							if ($minHisto > 30) {
								$minHisto = "30";
							} else {
								$minHisto = "00";
							}
							$heurecreneau = $heureHisto.":".$minHisto;
							if ($historique[0] == $lightondesc) {
								$tab_valuelights[$light_id][$jour][$heurecreneau] += 1;
							} else {
								$tab_valuelights[$light_id][$jour][$heurecreneau] -= 1;
							}
						}
					}
				}
				saveVariable('SMARTPRESENCE_LIGHTS_'.$zone, $tab_valuelights);
				// récupération historique des volets du jour
				foreach ($tab_shutters as $shutter_id) {
					if (!array_key_exists($shutter_id, $tab_valueshutters)) {
						$tab_valueshutters[$shutter_id] = array();
					}
					if (!array_key_exists($jour, $tab_valueshutters[$shutter_id])) {
						$tab_valueshutters[$shutter_id][$jour] = $tab_creneau;
					}
					$arrHistorique = array();
					$urlHistorique =  "https://api.eedomus.com/get?action=periph.history&periph_id=".$shutter_id."&api_user=".$apiu."&api_secret=".$apis.$paramDate;
					$result = httpQuery($urlHistorique, 'GET');
					$arrHistorique = sdk_json_decode(utf8_encode($result));
					if(array_key_exists("body", $arrHistorique) && array_key_exists("history", $arrHistorique["body"])) {
						$listeHistoriques = $arrHistorique["body"]["history"];
					}
					if(isset($listeHistoriques)) {
						$tab_valueshutters[$shutter_id][$jour] = $tab_creneau;
						// Parcours de l'historique
						foreach ($listeHistoriques as $historique) {
							$dateHisto = $historique[1];
							$heureHisto = substr($dateHisto, 11, 2);
							$minHisto = substr($dateHisto, 14, 2);
							if ($minHisto > 30) {
								$minHisto = "30";
							} else {
								$minHisto = "00";
							}
							$heurecreneau = $heureHisto.":".$minHisto;
							if ($historique[0] == $shutteropeneddesc) {
								$tab_valueshutters[$shutter_id][$jour][$heurecreneau] += 1;
							} else {
								$tab_valueshutters[$shutter_id][$jour][$heurecreneau] -= 1;
							}
						}
					}
				}
				saveVariable('SMARTPRESENCE_SHUTTERS_'.$zone, $tab_valueshutters);
			}
		} 
		$xml .= "<SMARTPRESENCE>";
		$xml .= "<MODE>".$mode."</MODE>";
		if ($debug == "update") {
			$xml .= "<UPDATE>".$nbmaj."-".$nblignemaj."</UPDATE>";
			$xml .= "<URL>".$url."</URL>";
			$xml .= "<RESULT>".$result."</RESULT>";
		}
		if ($debug == "yes") {
			$xml .= "<LIGHT1>".$lighton."-".$lightoff."-".$tab_lights[0]."</LIGHT1>";
			if (array_key_exists(1, $tab_lights)) {
				$xml .= "<LIGHT2>".$tab_lights[1]."</LIGHT2>";
			}
			$xml .= "<SHUTTER1>".$shutteropened."-".$shutterclosed."-".$tab_shutters[0]."</SHUTTER1>";
			if (array_key_exists(1, $tab_shutters)) {
				$xml .= "<SHUTTER2>".$tab_shutters[1]."</SHUTTER2>";
			}
			$xml .= "<TIME>".$jour."-".$heure."-(".$min.")</TIME>";
			$i = 0;
			while ($i < count($tab_lights)) {
				$light_id = $tab_lights[$i];
				if (array_key_exists($light_id, $tab_valuelights)) {
					if (array_key_exists($jour, $tab_valuelights[$light_id])) {
						$xml .= "<LIGHT_".$light_id."_".$jour.">";
						foreach ($tab_valuelights[$light_id][$jour] as $creneau => $valuecreneau) {
							$xml .= "<".str_replace(':','-',$creneau).">".$valuecreneau."</".str_replace(':','-',$creneau).">";
						}
						$xml .= "</LIGHT_".$light_id."_".$jour.">";
					}
				}
				$i++;
			}
		}
		$xml .= "<STATUS>".$status."</STATUS>";
		$xml .= "</SMARTPRESENCE>";
		sdk_header('text/xml');
		echo $xml;	
	}
?>
