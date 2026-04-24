<?php

namespace app\commands;

use app\models\SecurityEvents;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * EventGeneratorController - Continuously generates and writes events to the database
 * 
 * This command writes one security event record to the security_events table every 5 seconds.
 * It runs indefinitely until you stop it (Ctrl+C).
 * Great for testing real-time dashboard refreshes with test data.
 * 
 * Usage:
 *   php yii event-generator      # Start generating events
 *   php yii event-generator status # Show event statistics
 *   php yii event-generator clear  # Delete all generated events
 */
class EventGeneratorController extends Controller
{
    /**
     * Start generating events continuously
     * 
     * @return int exit code
     */
    public function actionIndex()
    {
        echo "Starting Event Generator for dashboard testing...\n";
        echo "Writing one event every 5 seconds. Press Ctrl+C to stop.\n\n";

        $counter = 0;
        $vendors = ['Firewall', 'IDS/IPS', 'Antivirus', 'SIEM', 'WAF', 'Proxy', 'VPN', 'Router'];
        $devices = ['Device-01', 'Device-02', 'Device-03', 'Device-04', 'Device-05'];
        $attacks = ['SQL Injection', 'XSS', 'DDoS', 'Brute Force', 'Malware', 'Phishing', 'Port Scan', 'Privilege Escalation'];
        $actions = ['block', 'allow', 'alert', 'quarantine', 'drop', 'reset'];
        $outcomes = ['success', 'failure', 'partial'];

        try {
            while (true) {
                $counter++;
                
                try {
                    // Create a new security event record
                    $event = new SecurityEvents();
                    $event->datetime = date('Y-m-d H:i:s');
                    $event->type = 'generated_test';
                    $event->cef_version = '0';
                    $event->cef_severity = rand(1, 10);
                    
                    $event->cef_event_class_id = (string)rand(100, 9999);
                    $event->cef_device_product = $devices[array_rand($devices)];
                    $event->cef_vendor = $vendors[array_rand($vendors)];
                    $event->cef_device_version = '1.0.0';
                    $event->cef_name = 'Test Security Event #' . $counter;
                    
                    // Optional fields for realistic test data
                    $event->device_action = $actions[array_rand($actions)];
                    $event->application_protocol = rand(0, 1) ? 'https' : 'http';
                    $event->device_host_name = 'host-' . rand(1, 5);
                    $event->device_address = '192.168.' . rand(0, 255) . '.' . rand(1, 254);
                    $event->source_address = '10.0.' . rand(0, 255) . '.' . rand(1, 254);
                    $event->source_port = rand(1024, 65535);
                    $event->destination_address = '172.16.' . rand(0, 255) . '.' . rand(1, 254);
                    $event->destination_port = rand(1, 1024);
                    $event->source_user_name = 'user' . rand(1, 100);
                    $event->destination_user_name = 'admin' . rand(1, 10);
                    $event->message = 'Generated test event for dashboard refresh';
                    $event->event_outcome = $outcomes[array_rand($outcomes)];
                    $event->attack_type = $attacks[array_rand($attacks)];
                    $event->transport_protocol = rand(0, 1) ? 'tcp' : 'udp';
                    $event->bytes_in = rand(1000, 1000000);
                    $event->bytes_out = rand(1000, 1000000);
                    $event->source_code = 'US';
                    $event->destination_code= 'US';
                    $event->source_country = 'United States';
                    $event->destination_country = 'United States';
                    $event->analyzed = false;
                    $event->raw_event = 'CEF:0|' . $event->cef_vendor . '|' . $event->cef_device_product . '|' . $event->cef_device_version . '|' . $event->cef_event_class_id . '|' . $event->cef_name . '|' . $event->cef_severity . '|';

                    // Save the event
                    if ($event->save()) {
                        echo '[' . date('Y-m-d H:i:s') . '] Event #' . $event->id . ' saved successfully (Counter: ' . $counter . ")\n";
                    } else {
                        echo '[' . date('Y-m-d H:i:s') . '] ERROR: Failed to save event. Errors: ' . json_encode($event->getErrors()) . "\n";
                    }

                } catch (\Exception $e) {
                    echo '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $e->getMessage() . "\n";
                }

                // Wait 5 seconds before next event
                sleep(5);
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            return ExitCode::UNSPECIFIED_ERROR;
        }
    }

    /**
     * Show status and statistics
     * 
     * @return int exit code
     */
    public function actionStatus()
    {
        $totalEvents = SecurityEvents::find()->count();
        $generatedEvents = SecurityEvents::find()->where(['type' => 'generated_test'])->count();
        
        echo "=== Security Events Statistics ===\n";
        echo "Total events in database: " . $totalEvents . "\n";
        echo "Generated test events: " . $generatedEvents . "\n";
        
        $recentEvent = SecurityEvents::find()->orderBy(['datetime' => SORT_DESC])->one();
        if ($recentEvent) {
            echo "\nMost recent event:\n";
            echo "  ID: " . $recentEvent->id . "\n";
            echo "  Datetime: " . $recentEvent->datetime . "\n";
            echo "  Vendor: " . $recentEvent->cef_vendor . "\n";
            echo "  Severity: " . $recentEvent->cef_severity . "\n";
        }
        
        return ExitCode::OK;
    }

    /**
     * Clear all generated test events
     * 
     * @return int exit code
     */
    public function actionClear()
    {
        if ($this->confirm('Are you sure you want to delete all generated test events from security_events table?')) {
            $count = SecurityEvents::deleteAll(['type' => 'generated_test']);
            echo "Deleted " . $count . " generated test events.\n";
            return ExitCode::OK;
        }
        
        echo "Clear operation cancelled.\n";
        return ExitCode::UNSPECIFIED_ERROR;
    }
}
