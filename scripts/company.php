<?php
    require "aps/2/runtime.php";     
    /**
    * @type("http://myweatherdemo.com/company/1.0")
    * @implements("http://aps-standard.org/types/core/resource/1.0")
    */    
    class company extends \APS\ResourceBase    
    {
        /**
        * @link("http://myweatherdemo.com/application/1.0")
        * @required
        */
        public $application;
        /**
         * @link("http://myweatherdemo.com/user/1.0[]")
         */
        public $users;    
        
        /**
        * @link("http://myweatherdemo.com/city/1.1[]")
        */
        public $cities;
        
        /**
        * @link("http://aps-standard.org/types/core/account/1.0")
        * @required
        */
        public $account;
        
        /**
        * @type("http://aps-standard.org/types/core/resource/1.0#Counter")
        * @unit("unit")
        * @title("Number of queries in MyWeatherDemo UI")
        */
        public $query_counter;
        
        /**
        * @type(string)
        * @title("Company identifier in MyWeatherDemo")
        */
        public $company_id;
        
        /**
        * @type(string)
        * @title("Login to MyWeatherDemo interface")
        */
        public $username;
        
        /**
        * @type(string)
        * @title("Password for MyWeatherDemo user")
        */
        public $password;

        // you can add your own methods as well, don't forget to make them private
        private function send_curl_request($verb, $path, $payload = ''){
            \APS\LoggerRegistry::get()->debug("company.php::REQUEST: " .
              $verb . " " . $path . " " . var_export($payload, true));
            $token = $this->application->token;
            $url = $this->application->url . $path;
            $headers = array(
                    'Content-type: application/json',
                    'x-provider-token: '. $token
            );
            $ch = curl_init();
            
            curl_setopt_array($ch, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_CUSTOMREQUEST => $verb,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => json_encode($payload)
            ));
            
            $response = json_decode(curl_exec($ch));
            
            curl_close($ch);
            \APS\LoggerRegistry::get()->debug("company.php::REPLY: " . var_export($response, true));
            return $response;
        }

        public function provision(){
            // to create a company in external service we need to pass country, city and name of the company
            // we can get them from linked core/account resource
            $request = array(
                'country' => $this->account->addressPostal->countryName,
                'city' => $this->account->addressPostal->locality,
                'name' => $this->account->companyName
            );
            $response = $this->send_curl_request('POST', "company/", $request);
            // need to save company_id in APSC, going to use that later to delete a resource in unprovision()
            // username and password will be used to login to MyWeatherDemo web interface
            $this->company_id = $response->{'id'};
            $this->username = $response->{'username'};
            $this->password = $response->{'password'};
            // preparing a subscription to Changed events type, designating handler as onLocationChange()
            $sub = new \APS\EventSubscription(\APS\EventSubscription::Changed, "onLocationChange");
            // we want to track linked core/account resource
            $sub->source->id=$this->account->aps->id;
            // getting access to controller conntector and subscribing
            $apsc = \APS\Request::getController();
            $apsc->subscribe($this, $sub);
        }

        /**
        * @verb(POST)
        * @path("/onLocationChange")
        * @param("http://aps-standard.org/types/core/resource/1.0#Notification",body)
        */
        public function onLocationChange($event) {
            // getting updated core/accont resource
            $apsc = \APS\Request::getController();
            $account = $apsc->getResource($event->source->id);
            // sending new city and country
            $request = array('city' => $account->addressPostal->locality,
              'country' => $account->addressPostal->countryName);
            $response = $this->send_curl_request('PUT', "company/" . $this->company_id, $request);
        }

        public function unprovision(){
            $this->send_curl_request('DELETE', "company/" . $this->company_id);
        }

	public function configure($new){
            $request = array('username' => $new->username, 'password' => $new->password);
            $this->send_curl_request('PUT', "company/" . $this->company_id, $request);
            // Get instance of the Notification Manager:
            $notificationManager = \APS\NotificationManager::getInstance();
            // Create Notification structure
            $notification = new \APS\Notification;
            $notification->message = new \APS\NotificationMessage("Company update");
            $notification->details = new \APS\NotificationMessage("Company details were updated");
            $notification->status = \APS\Notification::ACTIVITY_READY;
            $notification->packageId = $this->aps->package->id;
             
            $notificationResponse = $notificationManager->sendNotification($notification);
            // Store the Notification ID to update or remove it in other operations
            $this->notificationId = $notificationResponse->id;
        }

        public function retrieve() {
            $response = $this->send_curl_request('GET', "company/" . $this->company_id);
            $this->query_counter->usage = $response->{'weatherCount'};
        }

        /**
        * @type(string)
        * @title("Notification ID")
        */
        public $notificationId;

        /**
        * @verb(GET)
        * @path("/getTemperature")
        * @return(string,text)
        */
        public function getTemperature(){
            $response = $this->send_curl_request('GET', "company/" . $this->company_id);
            return $response->{'celsius'};
        }

    }
?>
