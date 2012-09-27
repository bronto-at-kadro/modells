<?php

/* @var $bootstrap Bootstrap */
/* @var $logger Zend_Log */
/* @var $api Bronto_Api */
/* @var $uuid Bronto_Util_Uuid */
/* @var $logsTable DbTable_Logs */
/* @var $contactsTable DbTable_Contacts */

// Bootstrap
define('LOG_APPEND', '_process');
define('CURRENT_SCRIPT', __FILE__);
require_once 'index.php';

try {

    /* @var $contactObject Bronto_Api_Contact */
    $contactObject = $api->getContactObject();

    /* @var $deliveryObject Bronto_Api_Delivery */
    $deliveryObject = $api->getDeliveryObject();
    
    /* @var $deliveryObject Bronto_Api_Delivery */
    $deliveryObject = $api->getDeliveryObject();

    /* @var $deliveryGroupObject Bronto_Api_DeliveryGroup */
    $deliveryGroupObject = $api->getDeliveryGroupObject();

    $validTypes = array('forwardtoafriend');
	
	$validStatuses = array('sent');

    //
    // Defaults
    $defaultDeliveryFromTs = false;
    $defaultDeliveryFrom   = false;
    $defaultDeliveryToTs   = false;
    $defaultDeliveryTo     = false;
    $types                 = $validTypes;
    $limit                 = 5000;

    //
    // Options
    $from = $bootstrap->getOpt('from');
	//$logger->info("deliveries.php line 45 - Bootstrap sent in $from");
	
    if ($from) {
        if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $from)) {
            throw new Exception("Invalid <green>--from</green> date: <white>{$from}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
        } else {
            $defaultDeliveryFromTs = strtotime($from);
            $defaultDeliveryFrom   = date('Y-m-d', $defaultDeliveryFromTs);
        }
    }

    $to = $bootstrap->getOpt('to');
    if ($to) {
        if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $to)) {
            throw new Exception("Invalid <green>--to</green> date: <white>{$to}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
        } else {
            $defaultDeliveryToTs = strtotime($to);
            $defaultDeliveryTo   = date('Y-m-d', $defaultDeliveryToTs);
        }
    }

    $type = $bootstrap->getOpt('type');
    if ($type) {
        $types = explode(',', $type);
        foreach ($types as $i => $type) {
            if (!in_array($type, $validTypes)) {
                throw new Exception("Invalid <green>--type</green>: <white>{$type}</white> (Must be one of: <yellow>" . implode('</yellow>, <yellow>', $validTypes) . '</yellow>)');
            }
        }
    }
	
	$logger->debug("deliveries.php line 76 - starting delivery process with from = $from and to = $to");

    $limit = $bootstrap->getOpt('limit');
    if ($limit && $limit < 1000) {
        throw new Exception("Invalid <green>--limit</green>: <white>{$limit}</white> (Must be greater than <yellow>1000</yellow>)");
    }

    // If we have already finished, keep track of it and try to export automatically...
    $finishedTypes = array();

    //
    // Iterate over each Delivery type
    foreach ($types as $deliveryType) {

        $logger->info("deliveries.php line 88 - Starting Process for $deliveryType deliveries");
        // Reset
        $deliveryFromTs = $defaultDeliveryFromTs;
        $deliveryFrom   = $defaultDeliveryFrom;
        $deliveryToTs   = $defaultDeliveryToTs;
        $deliveryTo     = $defaultDeliveryTo;

        // Holds reporting table instances
        $deliveryTables = array();

        //
        // If we didn't pass in a --from value, try to figure one out
        if (!$from) {
            // ... by getting the last successful run
            $logger->debug("No <green>--from</green> date passed, trying to determine a range to run for...");
            if ($lastRun = $logsTable->getLastSuccessfulRun($deliveryType)) {
                // ... and using its 'to' value as our start value
                $deliveryFromTs = $lastRun->delivery_to;
                $deliveryFrom   = date('Y-m-d', $deliveryFromTs);
                $logger->debug("Found last successful run, setting start date to: <green>{$deliveryFrom}</green>");
            } else {
                // ... or the first of this month
                $deliveryFrom   = date('Y-m-01');
                $deliveryFromTs = strtotime($deliveryFrom);
                $logger->debug("Found no successful runs, attempting with setting start date to: <green>{$deliveryFrom}</green>");
            }
        }

        // If we also didn't pass in --to, base it off start date
        if (!$to) {
            $deliveryToTs = strtotime($deliveryFrom . ' +1 week');
            $deliveryTo   = date('Y-m-d', $deliveryToTs);
        }

        //
        // Create initial log entry (not saved yet)
        $log = $logsTable->createRow();
        $log->delivery_type = $deliveryType;
        $log->delivery_from = $deliveryFrom;
        $log->delivery_to   = $deliveryTo;

        //
        // Have we already ran for this date range?
	
        $previousRun = $logsTable->getDeliveryRun($deliveryType, $deliveryFromTs);
	
        if ($previousRun) {
	//		$logger->info('deliveries.php line 134 - found a previous run');
            $previousDeliveryFromTs = $defaultDeliveryFromTs = $previousRun->delivery_from;
            $previousDeliveryFrom   = $defaultDeliveryFrom   = date('Y-m-d', strtotime($previousDeliveryFromTs));
            $previousDeliveryToTs   = $defaultDeliveryToTs   = $previousRun->delivery_to;
            $previousDeliveryTo     = $defaultDeliveryTo     = date('Y-m-d', strtotime($previousDeliveryToTs));
            if ($previousDeliveryToTs >= $deliveryToTs) {
                // Already ran
                if (empty($previousRun->message)) {
                    if (empty($previousRun->finished_at)) {
                        // Still running?
                        $lastModifiedTs = $previousRun->modified_at;
                        if (empty($previousRun->modified_at)) {
                            $lastModifiedTs = $previousRun->started_at;
                        }
                        if ((time() - $lastModifiedTs) > 7200) {
                            // Not updated in 2 hours... dead?
                            $logger->warn("<white>Possible</white> <lightred>dead</lightred> <white>run with ID:</white> {$previousRun->id}");
                            if (empty($previousRun->delivery_last)) {
                                throw new Exception("Possibly dead run has no deliveryLast value to restart with.");
                            } else {
                                // Update deliveryFrom to deliveryLast
                                $deliveryFromTs = $previousRun->delivery_last;
                                $deliveryFrom   = date('c', $deliveryFromTs);
                                if ($deliveryFromTs >= $deliveryToTs) {
                                    // deliveryLast is greater than deliveryTo, so this should be finished
                                    $logger->warn("deliveryFrom date ({$deliveryFrom}) would be newer than deliveryTo date ({$deliveryTo}) - marking finished...");
                                    $previousRun->finished_at = new Zend_Db_Expr('NOW()');
                                    $previousRun->save();
                                    exit;
                                } else {
                                    $logger->info("Trying to restart by setting new range:\t<green>{$deliveryFrom}</green> -> <green>{$deliveryTo}</green>");
                                    $log = $previousRun;
                                }
                            }
                        } else {
                            $logger->debug("<lightred>Running Now</lightred> <yellow>{$deliveryType}</yellow>:\t<green>{$deliveryFrom}</green> -> <green>{$deliveryTo}</green>");
                            exit;
                        }
                    } else {
                        // Completed
                        $finishedTypes[] = $deliveryType;
                        $logger->debug("Already completed <yellow>{$deliveryType}</yellow>:\t<green>{$deliveryFrom}</green> -> <green>{$deliveryTo}</green>");
                        continue;
                    }
                } else {
                    throw new Exception("Can not automatically restart run that previously failed: " . $previousRun->message);
                }
            } else {
                // Haven't ran yet
                $logger->warn("Trying to run for <yellow>{$deliveryType}</yellow>:\t<green>{$deliveryFrom}</green> -> <green>{$deliveryTo}</green>");
                $logger->err("  Previously ran for:\t<green>{$previousDeliveryFrom}</green> -> <green>{$previousDeliveryTo}</green>");
                exit;
            }
        }
			

        //
        // deliveryFrom can't be greater than deliveryTo
        if ($deliveryFromTs >= $deliveryToTs) {
            throw new Exception("deliveryFrom date (<yellow>{$deliveryFrom}</yellow>) is newer than deliveryTo date (<yellow>{$deliveryTo}</yellow>)!");
        }

        //
        // Are we trying to goto the future?
        $todayTs = strtotime(date('Y-m-d'));
        if ($deliveryToTs > $todayTs) {
            // Yes... so try to see if we can finish up one that failed previously
            $logger->debug("Search time would be in the future:\t<yellow>{$deliveryFrom}</yellow> -> <yellow>{$deliveryTo}</yellow>");
            $unfinishedRuns = $logsTable->getUnfinishedRuns();
            if (!empty($unfinishedRuns)) {
                // Farm out to another process
                $cmdFrom = date('Y-m-d', $unfinishedRuns['delivery_from']);
                $cmdTo   = date('Y-m-d', $unfinishedRuns['delivery_to']);
                foreach ($unfinishedRuns['unfinished_types'] as $type) {
                    $logger->debug("Found run that was unfinished, executing: <green>--from={$cmdFrom} --to={$cmdTo} --type={$type}</green>");
                    //$bootstrap->executeCommand('php ' . APPLICATION_PATH . DS . "deliveries.php --from={$cmdFrom} --to={$cmdTo} --type={$type}", true);
					 $output = shell_exec('php ' . APPLICATION_PATH . DS . "deliveries.php --from={$cmdFrom} --to={$cmdTo} --type={$type} &");
                }
            } else {
                $logger->debug("Found no runs that were unfinished, exiting...");
                $logger->warn('No successful or unfinished runs were found - someone should double-check for failures...');
            }
            exit;
        }

        // For determining how long the process took
        $startTime = microtime(true);

        try {

			$startDate = date('c',$deliveryFromTs);
			$deliveryFilter = array('start'=> array('value'=>$startDate, 'operator'=>'SameDay'), 'deliveryType' => array($deliveryType));
            $deliveries = $deliveryObject->readAll($deliveryFilter, true)->iterate();
			
			//
            // Iterate over each Delivery record
            foreach ($deliveries as $delivery /* @var $delivery Bronto_Api_Delivery_Row */) {

                // We use this a lot
                $deliveryDateTs = strtotime($delivery->start);

                //
                // Update log fields (may not actually save)
                $log->delivery_total += 1;
                $log->delivery_last   = $delivery->start;

                if ($deliveries->isNewPage()) {
                    if ($deliveries->getCurrentPage() == 1) {
                        $currentDeliveryDate = $deliveryFrom;
                        $logger->info("Starting <yellow>{$deliveryType}</yellow> deliveries...");
                    } else {
                        $currentDeliveryDate = date('c', $deliveryDateTs);
                    }

                    // Perform save after every 10 pages
                    if ($deliveries->getCurrentPage() == 1 || ($deliveries->getCurrentPage() % 10) == 0) {
                        $logger->info("  Page: <green>{$deliveries->getCurrentPage(4)}</green> Date: <green>{$currentDeliveryDate}</green> Total: <green>{$deliveries->getCurrentKey()}</green>");
                        $log->save();
                    }
                }

                //
                // Setup/Get deliveries table for this deliveryDate
                $year  = date('Y', $deliveryDateTs);
                $month = date('m', $deliveryDateTs);

                // Double check against deliveryTo
                if ($deliveryDateTs > $deliveryToTs) {
                    $logger->info("  Got deliveryDate <purple>{$delivery->deliveryDate}</purple> newer than <purple>{$deliveryTo}</purple>, skipping the rest...");
                    break;
                }

                // This process actually runs CREATE TABLE
                if (!isset($deliveryTables["{$year}_{$month}"])) {
                    // Save this DbTable instance to the "cache"
                    $deliveryTables["{$year}_{$month}"] = new DbTable_Deliveries(array('year' => $year, 'month' => $month));
                }

                // Sanity check
                $deliveryTable = $deliveryTables["{$year}_{$month}"];
                if (!$deliveryTable || !($deliveryTable instanceOf DbTable_Deliveries)) {
                    throw new Exception('  Something happened to the Delivery DbTable.');
                }

                if (empty($delivery->id)) {
                    $logger->warn("delivery has no id");
                    continue;
                }

                //
                // Save to deliveries Table
                $binDeliveryId   = $uuid->toBinary($delivery->id);
                $sqlNow          = date('Y-m-d H:i:s');
                $sqlDeliveryDate = date('Y-m-d H:i:s', $deliveryDateTs);
				
			
				
				
				//how to get contact id?  need to get recipient
				$recipients = $delivery->recipients;
				$contacts = array();
				foreach ($recipients as $recipient) {
				//	if ($recipient->type == 'contact') $contacts[] = $recipient->id;
					if ($recipient->type == 'subscriber') $contacts[] = $recipient->id;
				}
				
				
				foreach ($contacts as $contact)  {
					$binContactId = $uuid->toBinary($contact);
					$binMessageId = $uuid->toBinary($delivery->messageId);
					
					switch ($delivery->type) {
					
						case 'forwardtoafriend':
						
							$deliveryTable->insertOrUpdate(array(
								'id'   => $binDeliveryId,
								'start'      => $sqlDeliveryDate,
								'contact_id'   => $binContactId,
								'message_id' => $binMessageId,
								'id'  => $binDeliveryId,
								'type' => $delivery->type,
								'status' => $delivery->status
							), array(
								'start' => $sqlDeliveryDate,
							));
							break;
							
						default:
						
							$deliveryTable->insertOrUpdate(array(
								'id'   => $binDeliveryId,
								'start'      => $sqlDeliveryDate,
								'contact_id'   => $binContactId,
								'message_id' => $binMessageId,
								'id'  => $binDeliveryId,
								'type' => $delivery->type,
								'status' => $delivery->status
							), array(
								'start' => $sqlDeliveryDate,
							));
							break;
						
							
					  
					}
				
				}
            }

            // Finished
            $log->finished_at = new Zend_Db_Expr('NOW()');
            $log->save();

            // For determining how long the process took
            $totalTime = microtime(true) - $startTime;

            $logger->info("<white ongreen>Finished</white ongreen> <white>=)</white>");
            $logger->debug(sprintf("Total Processed: %s", number_format($deliveries->getCurrentKey())));
            if ($totalTime > 3600) {
                $logger->debug(sprintf("Total Time:      %.2f hrs", $totalTime / 3600));
            } elseif ($totalTime > 60) {
                $logger->debug(sprintf("Total Time:      %.2f mins", $totalTime / 60));
            } else {
                $logger->debug(sprintf("Total Time:      %.2f secs", $totalTime));
            }

        } catch (Exception $e) {
            $logger->emerg($e);
            if ($log) {
                $log->message = $e->getMessage();
                $log->save();
            }
            exit(1);
        }
    }

    // Try to export if we're done
    if ((isset($deliveryType) && $deliveryType == 'forwardtoafriend') || count($finishedTypes) === count($validTypes)) {
        // Looks like we finished all of them...
        $logger->debug("Finished all: <green>{$defaultDeliveryFrom}</green> -> <green>{$defaultDeliveryTo}</green>");

        //
        // Determine timeframe
		
		
		
		
        if (!empty($defaultDeliveryFromTs) && !empty($defaultDeliveryToTs)) {
          	$logger->debug("deliveries.php line 378 - calling contacts.php with from = $defaultDeliveryFrom and to = $defaultDeliveryTo");
			$cmdFrom = date('Y-m-d', time()-60*60*24);
				$logName = LOG_DIR.$cmdFrom.'_dailyreport.log';
                $command = 'php ' . APPLICATION_PATH . DS . "contacts.php --from={$defaultDeliveryFrom} --to={$defaultDeliveryTo}  >> $logName";
           
           // $bootstrap->executeCommand($command);
		    $output = shell_exec($command." &");
        }
		
    }

} catch (Exception $e) {
    $logger->emerg($e);
    exit(1);
}