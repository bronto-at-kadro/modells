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

    /* @var $activityObject Bronto_Api_Activity */
    $activityObject = $api->getActivityObject();
    
    /* @var $deliveryObject Bronto_Api_Delivery */
    $deliveryObject = $api->getDeliveryObject();

    /* @var $deliveryGroupObject Bronto_Api_DeliveryGroup */
    $deliveryGroupObject = $api->getDeliveryGroupObject();

    $validTypes = array(
        Bronto_Api_Activity::TYPE_SEND,
        Bronto_Api_Activity::TYPE_OPEN,
        Bronto_Api_Activity::TYPE_CLICK,
        Bronto_Api_Activity::TYPE_BOUNCE,
		Bronto_Api_Activity::TYPE_UNSUBSCRIBE
    );
	
	define('FIELD_ID_CAMPAIGN_CD', '0bc303e90000000000000000000000011bb5');
		define('FIELD_ID_CELL_CD', '0bc303e90000000000000000000000011bb7');
		define('FIELD_ID_RUNDATE','0bc503e90000000000000000000000011cda');
	/*
	 const BOUNCE_HARD_CONN_PERM    = 'conn_perm';
    const BOUNCE_HARD_SUB_PERM     = 'sub_perm';
    const BOUNCE_HARD_CONTENT_PERM = 'content_perm';
	*/

    //
    // Defaults
    $defaultActivityFromTs = false;
    $defaultActivityFrom   = false;
    $defaultActivityToTs   = false;
    $defaultActivityTo     = false;
    $types                 = $validTypes;
    $limit                 = 5000;

    //
    // Options
    $from = $bootstrap->getOpt('from');
    if ($from) {
        if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $from)) {
            throw new Exception("Invalid <green>--from</green> date: <white>{$from}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
        } else {
            $defaultActivityFromTs = strtotime($from);
            $defaultActivityFrom   = date('Y-m-d', $defaultActivityFromTs);
        }
    }

    $to = $bootstrap->getOpt('to');
    if ($to) {
        if (!preg_match("/^\d{4}-\d{2}(-\d{2})?$/", $to)) {
            throw new Exception("Invalid <green>--to</green> date: <white>{$to}</white> (Must be <yellow>YYYY-MM[-DD]</yellow>)");
        } else {
            $defaultActivityToTs = strtotime($to);
            $defaultActivityTo   = date('Y-m-d', $defaultActivityToTs);
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

    $limit = $bootstrap->getOpt('limit');
    if ($limit && $limit < 1000) {
        throw new Exception("Invalid <green>--limit</green>: <white>{$limit}</white> (Must be greater than <yellow>1000</yellow>)");
    }

    // If we have already finished, keep track of it and try to export automatically...
    $finishedTypes = array();

    //
    // Iterate over each Activity type
    foreach ($types as $activityType) {

        //
        // Reset
        $activityFromTs = $defaultActivityFromTs;
        $activityFrom   = $defaultActivityFrom;
        $activityToTs   = $defaultActivityToTs;
        $activityTo     = $defaultActivityTo;

        // Holds reporting table instances
        $activityTables = array();

        //
        // If we didn't pass in a --from value, try to figure one out
        if (!$from) {
            // ... by getting the last successful run
            $logger->debug("No <green>--from</green> date passed, trying to determine a range to run for...");
            if ($lastRun = $logsTable->getLastSuccessfulActivityRun($activityType)) {
                // ... and using its 'to' value as our start value
                $activityFromTs = $lastRun->activity_to;
                $activityFrom   = date('Y-m-d', $activityFromTs);
                $logger->debug("Found last successful run, setting start date to: <green>{$activityFrom}</green>");
            } else {
                // ... or the first of this month
                $activityFrom   = date('Y-m-01');
                $activityFromTs = strtotime($activityFrom);
                $logger->debug("Found no successful runs, attempting with setting start date to: <green>{$activityFrom}</green>");
            }
        }

        // If we also didn't pass in --to, base it off start date
        if (!$to) {
            $activityToTs = strtotime($activityFrom . ' +1 week');
            $activityTo   = date('Y-m-d', $activityToTs);
        }

        //
        // Create initial log entry (not saved yet)
        $log = $logsTable->createRow();
        $log->activity_type = $activityType;
        $log->activity_from = $activityFrom;
        $log->activity_to   = $activityTo;

        //
        // Have we already ran for this date range?
        $previousRun = $logsTable->getActivityRun($activityType, $activityFromTs);
        if ($previousRun) {
            $previousActivityFromTs = $defaultActivityFromTs = $previousRun->activity_from;
            $previousActivityFrom   = $defaultActivityFrom   = date('Y-m-d', $previousActivityFromTs);
            $previousActivityToTs   = $defaultActivityToTs   = $previousRun->activity_to;
            $previousActivityTo     = $defaultActivityTo     = date('Y-m-d', $previousActivityToTs);
            if ($previousActivityToTs >= $activityToTs) {
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
                            if (empty($previousRun->activity_last)) {
                                throw new Exception("Possibly dead run has no activityLast value to restart with.");
                            } else {
                                // Update activityFrom to activityLast
                                $activityFromTs = $previousRun->activity_last;
                                $activityFrom   = date('c', $activityFromTs);
                                if ($activityFromTs >= $activityToTs) {
                                    // activityLast is greater than activityTo, so this should be finished
                                    $logger->warn("activityFrom date ({$activityFrom}) would be newer than activityTo date ({$activityTo}) - marking finished...");
                                    $previousRun->finished_at = new Zend_Db_Expr('NOW()');
                                    $previousRun->save();
                                    exit;
                                } else {
                                    $logger->info("Trying to restart by setting new range:\t<green>{$activityFrom}</green> -> <green>{$activityTo}</green>");
                                    $log = $previousRun;
                                }
                            }
                        } else {
                            $logger->debug("<lightred>Running Now</lightred> <yellow>{$activityType}</yellow>:\t<green>{$activityFrom}</green> -> <green>{$activityTo}</green>");
                            exit;
                        }
                    } else {
                        // Completed
                        $finishedTypes[] = $activityType;
                        $logger->debug("Already completed <yellow>{$activityType}</yellow>:\t<green>{$activityFrom}</green> -> <green>{$activityTo}</green>");
                        continue;
                    }
                } else {
                    throw new Exception("Can not automatically restart run that previously failed: " . $previousRun->message);
                }
            } else {
                // Haven't ran yet
                $logger->warn("Trying to run for <yellow>{$activityType}</yellow>:\t<green>{$activityFrom}</green> -> <green>{$activityTo}</green>");
                $logger->err("  Previously ran for:\t<green>{$previousActivityFrom}</green> -> <green>{$previousActivityTo}</green>");
                exit;
            }
        }

        //
        // activityFrom can't be greater than activityTo
        if ($activityFromTs >= $activityToTs) {
            throw new Exception("activityFrom date (<yellow>{$activityFrom}</yellow>) is newer than activityTo date (<yellow>{$activityTo}</yellow>)!");
        }

        //
        // Are we trying to goto the future?
        $todayTs = strtotime(date('Y-m-d'));
        if ($activityToTs > $todayTs) {
            // Yes... so try to see if we can finish up one that failed previously
            $logger->debug("Search time would be in the future:\t<yellow>{$activityFrom}</yellow> -> <yellow>{$activityTo}</yellow>");
            $unfinishedRuns = $logsTable->getUnfinishedActiityRuns();
            if (!empty($unfinishedRuns)) {
                // Farm out to another process
                $cmdFrom = date('Y-m-d', $unfinishedRuns['activity_from']);
                $cmdTo   = date('Y-m-d', $unfinishedRuns['activity_to']);
                foreach ($unfinishedRuns['unfinished_types'] as $type) {
                    $logger->debug("Found run that was unfinished, executing: <green>--from={$cmdFrom} --to={$cmdTo} --type={$type}</green>");
                    //$bootstrap->executeCommand('php ' . APPLICATION_PATH . DS . "activities.php --from={$cmdFrom} --to={$cmdTo} --type={$type}", true);
					 $output = shell_exec('php ' . APPLICATION_PATH . DS . "activities.php --from={$cmdFrom} --to={$cmdTo} --type={$type} &");
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

            //
            // Iterate over each Activity record
            $activities = $activityObject->readAll(date('c', $activityFromTs), $limit, $activityType)->iterate();
            foreach ($activities as $activity /* @var $activity Bronto_Api_Activity_Row */) {

                // We use this a lot
                $activityDateTs = strtotime($activity->activityDate);

                //
                // Update log fields (may not actually save)
                $log->activity_total += 1;
                $log->activity_last   = $activity->activityDate;

                if ($activities->isNewPage()) {
                    if ($activities->getCurrentPage() == 1) {
                        $currentActivityDate = $activityFrom;
                        $logger->info("Starting <yellow>{$activityType}</yellow> Activities...");
                    } else {
                        $currentActivityDate = date('c', $activityDateTs);
                    }

                    // Perform save after every 10 pages
                    if ($activities->getCurrentPage() == 1 || ($activities->getCurrentPage() % 10) == 0) {
                        $logger->info("  Page: <green>{$activities->getCurrentPage(4)}</green> Date: <green>{$currentActivityDate}</green> Total: <green>{$activities->getCurrentKey()}</green>");
                        $log->save();
                    }
                }

                //
                // Setup/Get Activities table for this activityDate
                $year  = date('Y', $activityDateTs);
                $month = date('m', $activityDateTs);

                // Double check against activityTo
                if ($activityDateTs > $activityToTs) {
                    $logger->info("  Got activityDate <purple>{$activity->activityDate}</purple> newer than <purple>{$activityTo}</purple>, skipping the rest...");
                    break;
                }

                // This process actually runs CREATE TABLE
                if (!isset($activityTables["{$year}_{$month}"])) {
                    // Save this DbTable instance to the "cache"
                    $activityTables["{$year}_{$month}"] = new DbTable_Activities(array('year' => $year, 'month' => $month));
                }

                // Sanity check
                $activityTable = $activityTables["{$year}_{$month}"];
                if (!$activityTable || !($activityTable instanceOf DbTable_Activities)) {
                    throw new Exception('  Something happened to the Activity DbTable.');
                }

                if (empty($activity->contactId)) {
                    $logger->warn("activityId ({$activity->id}) has no contactId");
                    continue;
                }

                if (empty($activity->messageId)) {
                    $logger->warn("activityId ({$activity->id}) has no messageId");
                    continue;
                }

                if (empty($activity->deliveryId)) {
                    $logger->warn("activityId ({$activity->id}) has no deliveryId");
                    continue;
                }

                //
                // Save to Activities Table
                $binContactId    = $uuid->toBinary($activity->contactId);
                $binMessageId    = $uuid->toBinary($activity->messageId);
                $binDeliveryId   = $uuid->toBinary($activity->deliveryId);
                $sqlNow          = date('Y-m-d H:i:s');
                $sqlActivityDate = date('Y-m-d H:i:s', $activityDateTs);
                switch ($activity->trackingType) {
                    case Bronto_Api_Activity::TYPE_SEND:
                        $activityTable->insertOrUpdate(array(
                            'contact_id'   => $binContactId,
                            'message_id'   => $binMessageId,
                            'delivery_id'  => $binDeliveryId,
                            'message_name' => $activity->getMessage(true)->name,
                            'sent_at'      => $sqlActivityDate,
							'activity_type' => Bronto_Api_Activity::TYPE_SEND
                        ), array(
                            'sent_at' => new Zend_Db_Expr("IF(ISNULL(sent_at), '{$sqlActivityDate}', IF(sent_at > '{$sqlActivityDate}', '{$sqlActivityDate}', sent_at))"),
                        ));
                        break;
                    case Bronto_Api_Activity::TYPE_OPEN:
                        $activityTable->insertOrUpdate(array(
                            'contact_id'      => $binContactId,
                            'message_id'      => $binMessageId,
                            'delivery_id'     => $binDeliveryId,
                            'message_name'    => $activity->getMessage(true)->name,
                            'opened_count'    => 1,
                            'opened_at_first' => $sqlActivityDate,
                            'opened_at_last'  => $sqlActivityDate,
							'sent_at' => $sqlActivityDate,
							'activity_type' => Bronto_Api_Activity::TYPE_OPEN
                        ), array(
                            'opened_at_last' => $sqlActivityDate,
							'sent_at' => $sqlActivityDate,
                            'opened_count'   => new Zend_Db_Expr("opened_count + 1"),
                        ));
                        break;
                    case Bronto_Api_Activity::TYPE_CLICK:
                        $activityTable->insertOrUpdate(array(
                            'contact_id'       => $binContactId,
                            'message_id'       => $binMessageId,
                            'delivery_id'      => $binDeliveryId,
                            'message_name'     => $activity->getMessage(true)->name,
                            'clicked_count'    => 1,
                            'clicked_at_first' => $sqlActivityDate,
                            'clicked_at_last'  => $sqlActivityDate,
							'sent_at' => $sqlActivityDate,
							'activity_type' => Bronto_Api_Activity::TYPE_CLICK
                        ), array(
                            'clicked_at_last' => $sqlActivityDate,
						   'sent_at' => $sqlActivityDate,
                            'clicked_count'   => new Zend_Db_Expr("clicked_count + 1"),
                        ));
                        break;
					case Bronto_Api_Activity::TYPE_UNSUBSCRIBE:
                        $activityTable->insertOrUpdate(array(
                            'contact_id'       => $binContactId,
                            'message_id'       => $binMessageId,
                            'delivery_id'      => $binDeliveryId,
                            'message_name'     => $activity->getMessage(true)->name,
                            'sent_at'          => $sqlActivityDate,
							'activity_type' => Bronto_Api_Activity::TYPE_UNSUBSCRIBE
                        ), array(
                            'sent_at' => $sqlActivityDate
                        ));
                        break;
                    case Bronto_Api_Activity::TYPE_BOUNCE:
                        if ($contact = $activity->getContact()) {
                            if (empty($contact->email)) {
                                continue;
                            }			
							
                            if ($activity->isHardBounce()) {
                            // Always data...
							
							
								//re-look at this
								/*
								$customerNumber = $contact->getField($bootstrap->getBrontoField('CUSTOMERNUMBER'));
								$firstName      = $contact->getField($bootstrap->getBrontoField('firstname'));
								$lastName       = $contact->getField($bootstrap->getBrontoField('lastname'));
								
								
								$contactFields = array();
								$contactFields[FIELD_ID_CAMPAIGN_CD] = 'N/A';
								$contactFields[FIELD_ID_CELL_CD] = 'N/A';
								$contactFields[FIELD_ID_RUNDATE] = '2012-09-05 00:00:00';
								
								$contactFieldIds = array('0bc303e90000000000000000000000011bb5','0bc303e90000000000000000000000011bb7','0bc503e90000000000000000000000011cda');
								$contactIds = array();
								$contactIds[$activity->contactId] = TRUE;
								*/
								
								/*
								$contacts = $contactObject->readAll(array('type' => 'OR', 'id' => array($activity->contactId)), $contactFieldIds, false)->iterate();
								//should only return 1 contact
								foreach ($contacts as $contact) {
									
									foreach ($contact->fields as $idx => $fieldInfo) {
										if ($fieldInfo['content'] != NULL) {
											$contactFields[$fieldInfo['fieldId']] = $fieldInfo['content'];
											//$logger->info(" setting field and content values .  content = ".$fieldInfo['content']);
										}
									}
								
								}
								*/
								
							//	$logger->info('About to add bounce records');
								
							//stuff removed
							
							 $activityTable->insertOrUpdate(array(
                            'contact_id'      => $binContactId,
                            'message_id'      => $binMessageId,
                            'delivery_id'     => $binDeliveryId,
                            'message_name'    => $activity->getMessage(true)->name,
							'activity_type' => Bronto_Api_Activity::TYPE_BOUNCE,
							'sent_at' => $sqlActivityDate
                        ), array(
                            'sent_at' => $sqlActivityDate,
							'activity_type' => Bronto_Api_Activity::TYPE_BOUNCE
                        ));
							
						}	 //if $hardbounce							
							                         
                        } //if $contact = ...
						
                        break;
                }  //switch
				
			}  //foreach
            
			

            // Finished
            $log->finished_at = new Zend_Db_Expr('NOW()');
            $log->save();

            // For determining how long the process took
            $totalTime = microtime(true) - $startTime;

            $logger->info("<white ongreen>Finished</white ongreen> <white>=)</white>");
            $logger->debug(sprintf("Total Processed: %s", number_format($activities->getCurrentKey())));
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
    if ((isset($activityType) && $activityType == Bronto_Api_Activity::TYPE_UNSUBSCRIBE) || count($finishedTypes) === count($validTypes)) {
        // Looks like we finished all of them...
        $logger->debug("Finished all: <green>{$defaultActivityFrom}</green> -> <green>{$defaultActivityTo}</green>");

        //
        // Determine timeframe
		
		$logger->debug("activites.php line 434 - calling deliveries.php!");
		
		
        if (!empty($defaultActivityFromTs) && !empty($defaultActivityToTs)) {
				$cmdFrom = date('Y-m-d', time()-60*60*24);
				$logName = LOG_DIR.$cmdFrom.'_dailyreport.log';
                $command = 'php ' . APPLICATION_PATH . DS . "deliveries.php --from={$defaultActivityFrom} --to={$defaultActivityTo} >> $logName";
           // $bootstrap->executeCommand($command);
		    $output = shell_exec($command." &");
        }
		
    }

} catch (Exception $e) {
    $logger->emerg($e);
    exit(1);
}