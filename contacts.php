<?php

/* @var $bootstrap Bootstrap */
/* @var $logger Zend_Log */
/* @var $api Bronto_Api */
/* @var $uuid Bronto_Util_Uuid */
/* @var $logs DbTable_Logs */

// Bootstrap
define('LOG_APPEND', '_contacts');
define('CURRENT_SCRIPT', __FILE__);
require_once 'index.php';

define('FIELD_ID_CAMPAIGN_CD', '0bc303e90000000000000000000000011bb5');
define('FIELD_ID_CELL_CD', '0bc303e90000000000000000000000011bb7');
define('FIELD_ID_RUNDATE','0bc503e90000000000000000000000011cda');

try {

    /* @var $contactObject Bronto_Api_Contact */
    $contactObject = $api->getContactObject();
    $contactsTable = new DbTable_Contacts();
	
    // Options
    $from = $bootstrap->getOpt('from');
    if ($from && !preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $from)) {
        throw new Exception("Invalid <green>--from</green> date: <white>{$from}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
    } else if (!$from) {
        throw new Exception("Required argument <green>--from</green> missing");
    }
	
	
	//To is not needed for contacts, but it gets passed from script to script and is needed for export.php
	 $to = $bootstrap->getOpt('to');
    if ($from && !preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $from)) {
        throw new Exception("Invalid <green>--from</green> date: <white>{$from}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
    } else if (!$from) {
        throw new Exception("Required argument <green>--from</green> missing");
    }
	
	
	
	
	$logger->debug("contacts.php line 35 - starting process from = $from and to = $to");

    $limit = $bootstrap->getOpt('limit');
    if ($limit && $limit > 100) {
        throw new Exception("Invalid <green>--limit</green>: <white>{$limit}</white> (Must be less than <yellow>100</yellow>)");
    } elseif (!$limit) {
        $limit = 100;
    }

    $workerId = $bootstrap->getOpt('worker');
    if ($workerId && $workerId <= 1) {
        throw new Exception("Invalid <green>--worker</green> value: <white>{$workerId}</white> (Must be greater than <yellow>1</yellow>)");
    } elseif (!$workerId) {
        $workerId = 1;
    }

    //
    // Setup/Get Activities table for this activityDate
    $ts            = strtotime($from);
    $year          = date('Y', $ts);
    $month         = date('m', $ts);
    $activityTable = new DbTable_Activities(array('year'  => $year, 'month' => $month));
	$deliveryTable = new DbTable_Deliveries(array('year'  => $year, 'month' => $month));

    // Get an initial idea of how many we need to process
    $initialCount = 0;
    if (!$bootstrap->getOpt('skip-count')) {
        $initialCount = $activityTable->findWithNoEmail(null, null, true);
        $logger->info("Preparing to process: <lightgreen>{$initialCount}</lightgreen>");
    }

    // Determine offset per worker
    $offset = 0;if ($workerId > 1) {
        $offset = ($workerId - 1) * ($limit * 5);
        if ($initialCount > 0 && $offset >= $initialCount) {
            $offset = $initialCount / $workerId;
        }
    }

    // For determining how long the process took
    $startTime = microtime(true);
    $total     = 0;
	$contactIds = array();
 //   while ($rowset = $activityTable->getAllContactIds($limit, $offset)) {
 
  while ($rowset = $activityTable->findWithNoEmail($limit, $offset)) {
		//$logger->info('contacts.php line 69 - Looping with a set of activityTable entries.  offset = '.$offset);
        //
        // Create array of contactIds for API request
        
        $rowset     = $rowset->toArray();
		$logger->info("contacts.php line 95 - Rowset size = ".sizeof($rowset));
        foreach ($rowset as $row) {
            $contactId = $uuid->unstrip($uuid->binaryToString($row['contact_id']));
            $contactIds[$contactId] = true;
        }
		
		//$logger->info("ContactID size ".count($contactIds)." contact_ids in the activity table");

        if (($count = count($contactIds)) > 0 && ($total % 5000 == 0)) {
            $percent = 'N/A';
            if ($initialCount > 0) {
                $totalLeft = $initialCount - $total;
                $percent   = ($total > 0) ? ($initialCount / $totalLeft) : 0;
                $percent   = sprintf(' <white>%5.2f</white>%%', $percent);
            }
            $logger->info("  Processed:{$percent} (<yellow>{$total}</yellow>)");
        }
		$offset = $offset+$limit;
		
	}
	
			$logger->info("Contacts found after looking through activities = ".sizeof($contactIds));
	$offset = 0;
	
	//while ($rowset = $deliveryTable->getAllContactIds($limit, $offset)) {
	while ($rowset = $deliveryTable->findWithNoEmail($limit, $offset)) {
	//	$logger->info('contacts.php line 96 - Looping with a set of deliveryTable entries.  offset = '.$offset);
        //
        // Create array of contactIds for API request
        
        $rowset     = $rowset->toArray();
        foreach ($rowset as $row) {
            $contactId = $uuid->unstrip($uuid->binaryToString($row['contact_id']));
			if (!isset($contactIds[$contactId])) {
            	$contactIds[$contactId] = true;
				//$logger->info("$contactId is not set.  Adding");
			} else {
				//$logger->info("There is already a contact for $contactId");
			}
        }
		
		$offset = $offset+$limit;
		
	}
	
	$logger->info("Contacts found after looking through deliveries = ".sizeof($contactIds));

		
		$contactIdCopy = $contactIds; //creating a separate copy since we are removing elements from contactIds array
		

		
		for ($i=0; $i<sizeof($contactIdCopy); $i = $i+100) {
			if (($i+100) > sizeof($contactIdCopy)) {
				$targetContactIds = array_slice($contactIdCopy, $i);
			} else {
				$targetContactIds = array_slice($contactIdCopy, $i, 100);	
			}	

			
		//	$logger->info("targetcontactIds array size going into api call = ".sizeof($targetContactIds));
			
			// Find each contact by ID and update their e-mail
			$contactFieldIds = array('0bc303e90000000000000000000000011bb5','0bc303e90000000000000000000000011bb7','0bc503e90000000000000000000000011cda');
			$contacts = $contactObject->readAll(array('type' => 'OR', 'id' => array_keys($targetContactIds)), $contactFieldIds, false)->iterate();
			foreach ($contacts as $contact /* @var $contact Bronto_Api_Contact_Row */) {
				//$logger->info("Found ".$contact->email." .  Looping.");
				if ($contacts->isNewPage() && $contacts->count() != $count) {
					$logger->info("    Found <green>{$contacts->count()}/{$count}</green>");
				}
				$contactFields = array();
				$contactFields[FIELD_ID_CAMPAIGN_CD] = '';
				$contactFields[FIELD_ID_CELL_CD] = '';
				$contactFields[FIELD_ID_RUNDATE] = '';
				
				
				foreach ($contact->fields as $idx => $fieldInfo) {
					if ($fieldInfo['content'] != NULL) {
						$contactFields[$fieldInfo['fieldId']] = $fieldInfo['content'];
						//$logger->info(" setting field and content values .  content = ".$fieldInfo['content']);
					}
				}
				
					
				
				// Insert/Update
				$sqlNow       = date('Y-m-d H:i:s');
				$binContactId = $uuid->toBinary($contact->id);
				$contactsTable->insertOrUpdate(array(
					'contact_id'    => $binContactId,
					'contact_email' => $contact->email,
					'added_at'      => $sqlNow,
					'campaign_cd' => $contactFields[FIELD_ID_CAMPAIGN_CD],
					'cell_cd' => $contactFields[FIELD_ID_CELL_CD],
					'rundate' => $contactFields[FIELD_ID_RUNDATE],
				), array(
					'contact_email' => $contact->email,
					'modified_at'   => $sqlNow,
				));
				// Remove it from the list after updating
				unset($contactIds[$contact->id]);
				unset($targetContactIds[$contact->id]);
				$total++;
			}
			//$logger->info("    After loop with offeset = $i targetContactIds still has ".sizeof($targetContactIds));
		}

        //
        // Update failures so we don't infinite loop
        if (($count = count($contactIds)) > 0) {
            $logger->notice("  Unable to retrieve email address for <lightred>{$count}</lightred> Contacts");
        }
        $i = 1;
        foreach ($contactIds as $contactId => $null) {
            $logger->debug("    {$i}: {$contactId}");
            // Insert not found
            $sqlNow       = date('Y-m-d H:i:s');
            $binContactId = $uuid->toBinary($contactId);
            $contactsTable->insertOrUpdate(array(
                'contact_id'    => $binContactId,
                'contact_email' => DbTable_Contacts::NOT_FOUND_EMAIL,
                'added_at'      => $sqlNow,
            ), array(
                'contact_email' => DbTable_Contacts::NOT_FOUND_EMAIL,
                'modified_at'   => $sqlNow,
            ));
            $i++;
        }
    //} where old while loop used to end
	
 
    // For determining how long the process took
    $totalTime = microtime(true) - $startTime;

    $logger->info("<white ongreen>Finished</white ongreen> <white>=)</white>");
    $logger->debug(sprintf("Total Processed: %s", number_format($total)));
    if ($totalTime > 3600) {
        $logger->debug(sprintf("Total Time:      %.2f hrs", $totalTime / 3600));
    } elseif ($totalTime > 60) {
        $logger->debug(sprintf("Total Time:      %.2f mins", $totalTime / 60));
    } else {
        $logger->debug(sprintf("Total Time:      %.2f secs", $totalTime));
    }
	
	$logger->debug("contacts.php line 236 - calling export.php with from = $from and to = $to");
	$cmdFrom = date('Y-m-d', time()-60*60*24);
	$logName = LOG_DIR.$cmdFrom.'_dailyreport.log';
	$command = 'php ' . APPLICATION_PATH . DS . "export.php --from={$from} --to={$to}  >> $logName";
    $output = shell_exec($command." &");
     

} catch (Exception $e) {
    $logger->emerg($e);
    exit(1);
}