<?php

/* @var $bootstrap Bootstrap */
/* @var $logger Zend_Log */
/* @var $api Bronto_Api */
/* @var $uuid Bronto_Util_Uuid */
/* @var $logsTable DbTable_Logs */

// Bootstrap
define('LOG_APPEND', '_export');
define('CURRENT_SCRIPT', __FILE__);
define('CSV_DELIMITER', '|');
require_once 'index.php';

try {

    $exportOptions = $bootstrap->getConfig('export');
    $ftpOptions    = $bootstrap->getConfig('ftp');
    $dbOptions     = $bootstrap->getConfig('db');

		
		$action_info = array(
				  Bronto_Api_Activity::TYPE_SEND => array('code' => 'SE', 'desc' => 'Sent', 'dateField' => 'sent_at'),
				  Bronto_Api_Activity::TYPE_OPEN => array('code' => 'OP', 'desc' => 'Open', 'dateField' => 'opened_at_last'),
				  Bronto_Api_Activity::TYPE_CLICK => array('code' => 'CL', 'desc' => 'Click', 'dateField' => 'clicked_at_last'),
				  Bronto_Api_Activity::TYPE_BOUNCE => array('code' => 'HB', 'desc' => 'Hard Bounce', 'dateField' => array('modified_at', 'added_at')),
				  Bronto_Api_Activity::TYPE_UNSUBSCRIBE => array('code' => 'OO', 'desc' => 'Opt Out', 'dateField' => 'sent_at'),
				  'forwardtoafriend' => array('code' => 'FR', 'desc' => 'Forward', 'dateField' => 'start')
		
		
		);
	
	
	

    @mkdir($exportOptions['path'], 777, true);
    $reportPath = realpath($exportOptions['path']);

    //
    // Options
	
	
    $from   = $bootstrap->getOpt('from');
    $export = null;
    if ($from) {
        if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $from)) {
            throw new Exception("Invalid <green>--from</green> date: <white>{$from}</white> (Must be <yellow>YYYY-MM</yellow>)");
        } else {
            $fromTs = strtotime($from);
        }
        $to = $bootstrap->getOpt('to');
        if ($to) {
            if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $to)) {
                throw new Exception("Invalid <green>--to</green> date: <white>{$to}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
            } else {
                $toTs = strtotime($to);
            }
        }
    } else {
        // Get next export date range
        $nextExport = $logsTable->getNextExport();
        if ($nextExport) {
            $export = $nextExport;
            $from   = $fromTs = $export->activity_from;
            $to     = $toTs   = $export->activity_to;
        } else {
            throw new Exception('Nothing to export...');
        }
    }
	$logger->debug("export.php line 70 - starting export process with from = $from and to = $to");

    //
    // Get `from`pieces we need
    $fromYear  = date('Y', $fromTs);
    $fromMonth = date('m', $fromTs);
    $fromDay   = date('d', $fromTs);
    if ($fromMonth === 12) {
        // Next month is Jan. 1st of the next year
        $fromNextTs    = mktime(0, 0, 0, 1, 1, ($fromYear + 1));
        $fromNextMonth = date('m', $fromNextTs);
        $fromNextYear  = date('Y', $fromNextTs);
    } else {
        // Next month is Xxx. 1st of current year
        $fromNextTs    = mktime(0, 0, 0, ($fromMonth + 1), 1, $fromYear);
        $fromNextMonth = date('m', $fromNextTs);
        $fromNextYear  = date('Y', $fromNextTs);
    }

    //
    // Get `to` pieces we need
    if ($to) {
        if ($fromTs > $toTs) {
            throw new Exception("Cannot export backwards in time. Tried: {$from} -> {$to}");
        }
        $toYear  = date('Y', $toTs);
        $toMonth = date('m', $toTs);
        $toDay   = date('d', $toTs);
    } else {
        // No `to` value, use 1st of next month
        $toTs    = $fromNextTs;
        $toYear  = $fromNextYear;
        $toMonth = $fromNextMonth;
        $toDay   = date('d', $toTs);
    }

    //
    // Total days of export
    $days  = ($toTs - $fromTs) / 86400;


    //
    // Since we only store 1 month per table, execute a new export for each month (if we span multiple)
    if ($fromYear <= $toYear) {
        if ($fromYear < $toYear) {
            // Different years (ie: 2012-12-30 -> 2013-01-05)
            if ($fromMonth == 12 && $toDay == 1 && $toMonth == 1 && $toYear == $fromNextYear) {
                // Ignore differences when fromMonth=12, toDay=1, toMonth=1 and its the next year
            } else {
                $logger->info("Multiple years detected: {$fromYear} -> {$toYear}");
                for ($year = $fromYear; $year <= $toYear; $year++) {
                    // Export each year
                    if ($year == $fromYear) {
                        // Were on the `from` year; export till the end of that year
                        $command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$fromYear}-{$fromMonth}-{$fromDay} --to={$fromNextYear}-01-01";
                    } elseif ($year == $toYear) {
                        // Were on the `to` year; export from the beginning of that year
                        $command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$toYear}-01-01 --to={$toYear}-{$toMonth}-{$toDay}";
                    } else {
                        // We're in between years, export the whole year
                        $command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$year}-01-01 --to=" . ($year + 1) . '-01-01';
                    }
                  //  $process = $bootstrap->executeCommand($command, true);
				  
				  $output = shell_exec($command." &");  //NEW CODE
				  $logger->debug("export.php line 135 - executing = $command");
                }
                $logger->info("Done processing: {$fromYear}-{$fromMonth} -> {$toYear}-{$toMonth}");
                exit;
            }
        } else {
            // If we got here, the export is within the same year
            if ($fromMonth <= $toMonth) {
                if ($fromMonth < $toMonth) {
                    // Different months (ie: 2012-01-30 -> 2012-02-05)
                    if ($toDay == 1 && $toMonth == $fromNextMonth) {
                        // Ignore differences when toDay=1, and its the next month
                    } else {
                        $logger->info("Multiple months detected: {$fromMonth} -> {$toMonth}");
                        for ($month = $fromMonth; $month <= $toMonth; $month++) {
                            // Export each month
                            if ($month == $fromMonth) {
                                // Were on the `from` month; export till the end of that month
                                $command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$fromYear}-{$fromMonth}-{$fromDay} --to={$fromYear}-{$fromNextMonth}-01";
                            } elseif ($month == $toMonth) {
                                // Were on the `to` month; export from the beginning of that month
                                $command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$toYear}-{$toMonth}-01 --to={$toYear}-{$toMonth}-{$toDay}";
                            } else {
                                // We're in between months, export the whole month
                                $monthNext = date('m', mktime(0, 0, 0, ($month + 1), 1, $fromYear));
                                $month     = date('m', mktime(0, 0, 0, $month, 1, $fromYear));
                                $command   = 'php ' . APPLICATION_PATH . DS . "export.php --from={$fromYear}-{$month}-01 --to={$toYear}-{$monthNext}-01";
                            }
                           // $process = $bootstrap->executeCommand($command, true);
						    $logger->debug("export.php line 164 - executing = $command");
						   $output = shell_exec($command." &");  //NEW CODE
                        }
                        $logger->info("Done processing: {$fromYear}-{$fromMonth} -> {$toYear}-{$toMonth}");
                        exit;
                    }
                } else {
                    // Same month (continue as normal)
                }
            } else {
                throw new Exception("Export `from` month value cannot be greater than `to` month. Tried: {$fromMonth} -> {$toMonth}");
            }
        }
    } else {
        throw new Exception("Export `from` year value cannot be greater than `to` year. Tried: {$fromYear} -> {$toYear}");
    }

    if (!$export) {
        $export = $logsTable->createRow();
        $export->activity_from = $fromTs;
        $export->activity_to   = $toTs;
    }

    //
    // Setup/Get Activities table for this activityDate
    $activityTable = new DbTable_Activities(array('year' => $fromYear, 'month' => $fromMonth));
	$deliveryTable = new DbTable_Deliveries(array('year' => $fromYear, 'month' => $fromMonth));
	$contactTable = new DbTable_Contacts();



    try {

        // Start
        $logsTable->markActivityExport($export, 'started');

        // For determining how long the process took
        $startTime = microtime(true);
		
		$fileName = 'modells_daily_response_file_'.sprintf('%04d_%02d_%02d.txt', $fromYear, $fromMonth, $fromDay);
        $report1FileName = "{$fileName}";
        $report1FilePath = $reportPath . DIRECTORY_SEPARATOR . $report1FileName;

        $filePath = $reportPath . DIRECTORY_SEPARATOR . $fileName;
        $zipName  = str_replace('.txt', '.zip', $fileName);
        $zipPath  = str_replace('.txt', '.zip', $filePath);

        // Don't to accidentally use these
		$exportedFilename = $fileName;
        unset($filePath);
     //   unset($fileName);

        foreach (array('1' => $report1FilePath) as $i => $filePath) {
            if (file_exists($filePath)) {
                // Export exists only (failed?)
                $logger->warn("Export file #{$i} found. Removing for retry...");
                @unlink($filePath);
            }
        }
		
	
	
	
		//1.) query activity table for Send, Open, Click, Unsubscribe
		
		$activityCountSelect = $activityTable->select();
		$activityCountSelect->from($activityTable->info('name'), array('count(*) as amount'));
		$rows = $activityTable->fetchAll($activityCountSelect);
		if ($rows[0]->amount > 0) {
		
			$activitySelect = $activityTable->select();
			$activitySelect->setIntegrityCheck(false);
			$activitySelect->from(array('c' => 'contacts'),
							array('contact_id', 'contact_email', 'campaign_cd', 'cell_cd', 'rundate')
							)
							->join(
								array('a' => $activityTable->info('name')),
										'c.contact_id = a.contact_id',
										array('sent_at', 'activity_type', 'clicked_at_last', 'opened_at_last')
							)
						->where('a.sent_at >= ?',$from)
						->where('a.sent_at < ?', $to);
							
			 $result = $activityTable->fetchAll($activitySelect);
			 
			 $toWrite = array();
			 $activityRows = array();
			 $deliveryRows = array();
			 $contactRows = array();
			 
			 foreach ($result as $row) {
				$rowString = '';
				$contact_id = $uuid->unstrip($uuid->binaryToString($row->contact_id));
				$contact_email = $row->contact_email;
				$campaign_cd = $row->campaign_cd;
				$cell_cd = $row->cell_cd;
				//$rundate = $row->rundate;
				if (($row->rundate != '') && ($row->rundate != '0000-00-00 00:00:00')) {
					$rundate = date('Y-m-d h:i:s', strtotime($row->rundate));	
				} else {
					$rundate = '';
				}
			
				$action_type_code = $action_info[$row->activity_type]['code'];
				$action_type_desc = $action_info[$row->activity_type]['desc'];
		
				
					
				switch ($row->activity_type) {
					case Bronto_Api_Activity::TYPE_SEND:
					case Bronto_Api_Activity::TYPE_BOUNCE:
					case Bronto_Api_Activity::TYPE_UNSUBSCRIBE:
					case Bronto_Api_Activity::TYPE_OPEN:
					case Bronto_Api_Activity::TYPE_CLICK:
						$action_date = date('Y-m-d h:i:s', $row->sent_at);
						break;
				}
				
				
				
				
				
				$rowString = $contact_id.CSV_DELIMITER.$contact_email.CSV_DELIMITER.$campaign_cd.CSV_DELIMITER.$cell_cd.CSV_DELIMITER.$rundate.CSV_DELIMITER.$action_type_desc.CSV_DELIMITER.$action_type_code.CSV_DELIMITER.$action_date;
				$activityRows[] = $rowString;	 
			 }
			 $toWrite = array_merge($toWrite, $activityRows);
			 
			 $logger->info('export.php line 291 - Added '.sizeof($activityRows).' rows from Activities for Export');
		 } else {
		 	$logger->info('No rows added from Activities');
		 }
		 
		 //2.)  query deliveries table for Forwards
		 
		$deliveryCountSelect = $deliveryTable->select();
		$deliveryCountSelect->from($deliveryTable->info('name'), array('count(*) as amount'));
		$rows = $deliveryTable->fetchAll($deliveryCountSelect);
		if ($rows[0]->amount > 0) {
		
			$deliverySelect = $deliveryTable->select();
			$deliverySelect->setIntegrityCheck(false);
			$deliverySelect->from(array('c' => 'contacts'),
							array('contact_id', 'contact_email', 'campaign_cd', 'cell_cd', 'rundate'))
				->join(array('d' => $deliveryTable->info('name')),
							'c.contact_id = d.contact_id',
							array('start', 'type')
				)
				->where('d.start >= ?',$from)
				->where('d.start < ?', $to);
							
							
							
			 $result = $deliveryTable->fetchAll($activitySelect);
			 
			 
			 foreach ($result as $row) {
				$rowString = '';
				$contact_id = $uuid->unstrip($uuid->binaryToString($row->contact_id));
				$contact_email = $row->contact_email;
				$campaign_cd = $row->campaign_cd;
				$cell_cd = $row->cell_cd;
				$rundate = $row->rundate;	
				$action_type_code = $action_info[$row->activity_type]['code'];
				$action_type_desc = $action_info[$row->activity_type]['desc'];
				$action_date = $row->start;
				$rowString = $contact_id.CSV_DELIMITER.$contact_email.CSV_DELIMITER.$campaign_cd.CSV_DELIMITER.$cell_cd.CSV_DELIMITER.$rundate.CSV_DELIMITER.$action_type_desc.CSV_DELIMITER.$action_type_code.CSV_DELIMITER.$action_date;
				$deliveryRows[] = $rowString;	 
			 }
			 
				$toWrite = array_merge($toWrite, $deliveryRows);
			  $logger->info('export.php line 329 - Added '.sizeof($deliveryRows).' rows from deliveries for export');
		 } else {
		 	$logger->info('No rows added from Deliveries');
		 }
		 
		 
		 
		 //3.) query contacts table for Hard Bounces
		 /*
		 
			$contactSelect = $contactTable->select();
		$contactSelect->setIntegrityCheck(false);
		$contactSelect->from($contactTable->info('name'),
						array('contact_id', 'contact_email', 'campaign_cd', 'cell_cd', 'rundate', 'hard_bounce_count', 'hard_bounce_last_date'))
						->where('hard_bounce_count > 0');

						
		 $result = $contactTable->fetchAll($contactSelect);
		 
		 
		 foreach ($result as $row) {
			$rowString = '';
			$contact_id = $uuid->unstrip($uuid->binaryToString($row->contact_id));
			$contact_email = $row->contact_email;
			$campaign_cd = $row->campaign_cd;
			$cell_cd = $row->cell_cd;
			$rundate = $row->rundate;	
			$action_type_code = $action_info[Bronto_Api_Activity::TYPE_BOUNCE]['code'];
			$action_type_desc = $action_info[Bronto_Api_Activity::TYPE_BOUNCE]['desc'];
			$action_date = $row->hard_bounce_last_date;
			$rowString = $contact_id.CSV_DELIMITER.$contact_email.CSV_DELIMITER.$campaign_cd.CSV_DELIMITER.$cell_cd.CSV_DELIMITER.$rundate.CSV_DELIMITER.$action_type_desc.CSV_DELIMITER.$action_type_code.CSV_DELIMITER.$action_date;
			$deliveryRows[] = $rowString;	 
		 }
		 
		  $logger->info('Added '.sizeof($deliveryRows).' rows from deliveries');
		
		*/
		
		
	//	$toWrite = array_merge($toWrite, $deliveryRows);
	//	$toWrite = array_merge($toWrite, $contactRows);
		
		
		
		
		
		
		$headers = array(
            'contact_id',
            'contact_email',
            'campaign_cd',
            'cell_cd',
            'rundate',
            'action_type_desc',
            'action_type_code',
            'action_date'
        );
		
		//exit();

        $headers  = implode('|', $headers) . PHP_EOL;
		$logger->info($report1FilePath);
        $handle   = fopen($report1FilePath, 'w');
		fwrite($handle, $headers);
		foreach ($toWrite as $line) {
			fwrite($handle, $line.PHP_EOL);
		}
		fclose($handle);
		
		
		
		
		//now apply PGP encryption
		$encryptedReportName = $exportedFilename.'.pgp';
		$encryptedReportFile = $reportPath . DIRECTORY_SEPARATOR . $encryptedReportName;
		
		system("gpg --recipient ndcftp@harte-hanks.com --output {$encryptedReportFile} --always-trust --encrypt {$report1FilePath}");
		
		
		
/*
        if (file_exists($zipPath)) {
            if ($bootstrap->getOpt('restart')) {
                $logger->warn("Export archive found. Removing because of <lightred>--restart</lightred>");
                @unlink($zipPath);
            } else {
                // Zip exists (retry uploading only)
                $logger->warn("Export archive found. Skipping export and retrying upload...");
            }
        }
		*/

		/*
        if (!file_exists($zipPath)) {
            // Export it #1
            $activityTable->exportCsv($report1FilePath, $fromTs, $toTs);
            $fileSize1 = @filesize($report1FilePath);
            if ($fileSize1 <= 64) {
                @unlink($report1FilePath);
                throw new Exception("Not continuing, too small: {$report1FilePath} ({$fileSize1} bytes)");
            }

           

            // Zip it
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZIPARCHIVE::CREATE) !== true) {
                throw new Exception(sprintf('Could not open archive for writing: <yellow>%s</yellow>', $zipPath));
            }
            if ($zip->addFile($report1FilePath, $report1FileName) !== true) {
                throw new Exception(sprintf('Could not add file/path to archive: <yellow>%s</yellow>', $report1FileName));
            }
           
            $zip->close();

            // Remove export now that we are done
            @unlink($report1FilePath);
            @unlink($report2FilePath);
        }

        $zipSize = @filesize($zipPath);
        if ($zipSize <= 128) {
            throw new Exception("Not continuing, too small: {$zipPath} ({$zipSize} bytes)");
        }
		*/

        //
        // Upload it
		
		
		
		
	
	
			$connection = ssh2_connect('webftp.harte-hanks.com', 22);
			ssh2_auth_password($connection, 'modells-kadro-prod', 'zePRH8MO');
			$sftp = ssh2_sftp($connection);
			$resFile = fopen("ssh2.sftp://{$sftp}/to_hh/".$encryptedReportName, 'w');
			if ($resFile === FALSE) {
				throw new Exception("Could not connect to SFTP Host");
			}
			$srcFile = fopen($encryptedReportFile, 'r');
			if ($srcFile === FALSE) {
				throw new Exception("Could not open local file for stream");
			}
			$writtenBytes = stream_copy_to_stream($srcFile, $resFile);
			if ($writtenBytes <= 0) {
			
				throw new Exception("Nothing was written during export");
			}
			fclose($resFile);
			fclose($srcFile);

		
		
		
	



	
		
		
		
		
		
	
        
        // Finish
        $logsTable->markActivityExport($export, 'finished');
		$logsTable->markDeliveryExport($export, 'finished');

        // For determining how long the process took
        $totalTime = microtime(true) - $startTime;

        $logger->info("<white ongreen>Finished</white ongreen> <white>=)</white>");
        if ($totalTime > 3600) {
            $timeString = sprintf("<bold>Total Time:</bold> %.2f hrs", $totalTime / 3600);
            $logger->debug($timeString);
        } elseif ($totalTime > 60) {
            $timeString = sprintf("<bold>Total Time:</bold> %.2f mins", $totalTime / 60);
            $logger->debug($timeString);
        } else {
            $timeString = sprintf("<bold>Total Time:</bold> %.2f secs", $totalTime);
            $logger->debug($timeString);
        }

/*
        $sizeString = '<bold>Total Size:</bold> ' . format_bytes($zipSize);
        $logger->debug($sizeString);
*/
        // Send notification email
		//sendMail($subject = null, $from = null, $to = null, $parameters = array())
		
        $bootstrap->sendMail("[184.73.208.159] Modell's Daily Report Uploaded: {$report1FileName}", 'bronto@kadro.com', 'jhodak@kadro.com', array(
            'header'  => "Finished Uploading: <span style=\"font-family: monospace\">ON the ftp server at :  $report1FileName}</span>",
            'content' => "{$timeString}",
        ));
		

    } catch (Exception $e) {
        $logger->emerg($e);
        exit(1);
    }

} catch (Exception $e) {
    $logger->emerg($e);
    exit(1);
}
