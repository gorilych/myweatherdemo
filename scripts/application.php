<?php
    require "aps/2/runtime.php";    
    /**
    * @type("http://myweatherdemo.com/application/1.0")
    * @implements("http://aps-standard.org/types/core/application/1.0")
    */
    class app extends \APS\ResourceBase  
    {
        /**
        * @link("http://myweatherdemo.com/company/1.0[]")
        */
        public $companies;
        /**
         * @type(string)
         * @title("URL")
         */
        public $url;
              
        /**
         * @type(string)
         * @title("Token")
         * @encrypted
         */
        public $token;         

        public function upgrade () {
          $apsc = \APS\Request::getController();
          
          // retrieve the list of resource 
          $companies = $apsc->getResources("implementing(http://myweatherdemo.com/company/1.0)");
          foreach ($companies as $company) {
            // preparing a subscription to Changed events type, designating handler as onLocationChange()
            $sub = new \APS\EventSubscription(\APS\EventSubscription::Changed, "onLocationChange");
            $sub->source->id=$company->account->aps->id;
            $apsc->subscribe($company, $sub);
          }
        }
    }
?>
