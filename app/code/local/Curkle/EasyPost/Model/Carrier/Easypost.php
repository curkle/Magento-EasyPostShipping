<?php  
    class Curkle_EasyPost_Model_Carrier_Easypost     
		extends Mage_Shipping_Model_Carrier_Abstract
		implements Mage_Shipping_Model_Carrier_Interface
	{  
        protected $_code = 'easypost';
        const ONLY_LOWEST_RATE = 1;

        /**
         * Rate request data
         *
         * @var Mage_Shipping_Model_Rate_Request|null
         */
        protected $_request = null;


        /**
         * Rate result data
         *
         * @var Mage_Shipping_Model_Rate_Result|null
         */
        protected $_result = null;



        /** 
        * Collect rates for this shipping method based on information in $request 
        * 
        * @param Mage_Shipping_Model_Rate_Request $data 
        * @return Mage_Shipping_Model_Rate_Result 
        */  
        public function collectRates(Mage_Shipping_Model_Rate_Request $request){


            if (!$this->getConfigFlag('active')) {
                return false;
            }
            $this->setRequest($request);
          //  print_r($this);exit;
            $this->_result = $this->_getQuotes();
            $this->_updateFreeMethodQuote($request);
            $result = $this->getResult();


            return $result;
        }  

		/**
		 * Get allowed shipping methods
		 *
		 * @return array
		 */
		public function getAllowedMethods()
		{
            return array(
                'USPS' => 'USPS',
            );
		}


        private function _createParcel(Varien_Object $request)
        {

        }

        private function _getTracking($trackings)
        {

        }

        private function _getLowestRate(Varien_Object $request)
        {

        }

        /**
         * @return mixed
         */
        private function _getQuotes()
        {
            $api_key = Mage::helper('core')->decrypt($this->getConfigData('api_key'));

            \EasyPost\EasyPost::setApiKey($api_key);
            $r = $this->_rawRequest;
            $to_address = \EasyPost\Address::create(
                array(
                    "name"    => "",
                    "street1" => "",
                    "street2" => "",
                    "city"    => $r->getOrigCountry(),
                    "state"   => $r->getOrigRegionCode(),
                    "zip"     => $r->getOrigPostal(),
                    "phone"   => "",
                    "country" => $r->getOrigCountry()
                )
            );
            $from_address = \EasyPost\Address::create(
                array(
                    "company" => "",
                    "street1" => "",
                    "city"    => "",
                    "state"   => $r->getDestRegionCode(),
                    "zip"     => $r->getDestPostal(),
                    "phone"   => '',
                    "country" => $r->getDestCountry()
                )
            );
            $parcel = \EasyPost\Parcel::create(
                array(
                    //"predefined_package" => "LargeFlatRateBox",
                    "weight" => $r->getWeight()
                )
            );

            $shipment = $this->_createShipment($to_address, $from_address, $parcel);


            try {

                if(self::ONLY_LOWEST_RATE == $this->getConfigData('only_lowest_option'))
                {
                    $rates = $shipment->lowest_rate();
                    return $this->_paserSingleResponse($rates);
                }
                else{
                    $rates = $shipment->rates;

                    return $this->_paserMutiResponse($rates);
                }

            } catch (Exception $e) {
                return null;
            }

        }

        private function _paserSingleResponse($rateResponse)
        {
            $result = Mage::getModel('shipping/rate_result');
            $rate = Mage::getModel('shipping/rate_result_method');
            /* @var $rate Mage_Shipping_Model_Rate_Result_Method */

            $rate->setCarrier($this->_code);
            /**
             * getConfigData(config_key) returns the configuration value for the
             * carriers/[carrier_code]/[config_key]
             */

            $rate->setCarrierTitle($rateResponse->carrier);

            $rate->setMethod($rateResponse->carrier.'| '.$rateResponse->service);
            $rate->setMethodTitle($rateResponse->carrier.'| '.$rateResponse->service);

            $rate->setPrice($rateResponse->rate);
            $rate->setCost(0);

            $result->append($rate);

            return $result;
        }

        private function _paserMutiResponse($rateResponses)
        {
            $result = Mage::getModel('shipping/rate_result');
            foreach ($rateResponses as $rateResponse)
            {
                $rate = Mage::getModel('shipping/rate_result_method');
                /* @var $rate Mage_Shipping_Model_Rate_Result_Method */

                $rate->setCarrier($this->_code);
                /**
                 * getConfigData(config_key) returns the configuration value for the
                 * carriers/[carrier_code]/[config_key]
                 */

                $rate->setCarrierTitle($rateResponse->carrier);

                $rate->setMethod($rateResponse->carrier.'| '.$rateResponse->service);
                $rate->setMethodTitle($rateResponse->carrier.'| '.$rateResponse->service);

                $rate->setPrice($rateResponse->rate);
                $rate->setCost(0);

                $result->append($rate);
            }

            return $result;
        }

        /**
         * Get result of request
         *
         * @return mixed
         */
        public function getResult()
        {
            return $this->_result;
        }

        /**
         * Prepare and set request to this instance
         *
         * @param Mage_Shipping_Model_Rate_Request $request
         * @return Curkle_EasyPost_Model_Carrier_Easypost
         */
        public function setRequest(Mage_Shipping_Model_Rate_Request $request)
        {
            $this->_request = $request;
            $r = new Varien_Object();

            if ($request->getOrigCountry()) {
                $origCountry = $request->getOrigCountry();
            } else {
                $origCountry = Mage::getStoreConfig(
                    Mage_Shipping_Model_Shipping::XML_PATH_STORE_COUNTRY_ID,
                    $request->getStoreId()
                );
            }
            $r->setOrigCountry(Mage::getModel('directory/country')->load($origCountry)->getIso2Code());
            if ($request->getOrigRegionCode()) {
                $origRegionCode = $request->getOrigRegionCode();
            } else {
                $origRegionCode = Mage::getStoreConfig(
                    Mage_Shipping_Model_Shipping::XML_PATH_STORE_REGION_ID,
                    $request->getStoreId()
                );
            }
            if (is_numeric($origRegionCode)) {
                $origRegionCode = Mage::getModel('directory/region')->load($origRegionCode)->getCode();
            }
            $r->setOrigRegionCode($origRegionCode);
            if ($request->getOrigPostcode()) {
                $r->setOrigPostal($request->getOrigPostcode());
            } else {
                $r->setOrigPostal(Mage::getStoreConfig(
                    Mage_Shipping_Model_Shipping::XML_PATH_STORE_ZIP,
                    $request->getStoreId()
                ));
            }
            if ($request->getOrigCity()) {
                $r->setOrigCity($request->getOrigCity());
            } else {
                $r->setOrigCity(Mage::getStoreConfig(
                    Mage_Shipping_Model_Shipping::XML_PATH_STORE_CITY,
                    $request->getStoreId()
                ));
            }
            if ($request->getDestCountryId()) {
                $destCountry = $request->getDestCountryId();
            } else {
                $destCountry = self::USA_COUNTRY_ID;
            }

            $r->setDestCountry(Mage::getModel('directory/country')->load($destCountry)->getIso2Code());
            $r->setDestRegionCode($request->getDestRegionCode());
            if ($request->getDestPostcode()) {
                $r->setDestPostal($request->getDestPostcode());
            } else {
            }
            $weight = $this->getTotalNumOfBoxes($request->getPackageWeight());
            $weight = $this->_getCorrectWeight($weight);
            $r->setWeight($weight);
            if ($request->getFreeMethodWeight()!=$request->getPackageWeight()) {
                $r->setFreeMethodWeight($request->getFreeMethodWeight());
            }
            $r->setValue($request->getPackageValue());
            $r->setValueWithDiscount($request->getPackageValueWithDiscount());
            if ($request->getUpsUnitMeasure()) {
                $unit = $request->getUpsUnitMeasure();
            } else {
                $unit = $this->getConfigData('unit_of_measure');
            }
            $r->setUnitMeasure($unit);
            $r->setIsReturn($request->getIsReturn());
            $r->setBaseSubtotalInclTax($request->getBaseSubtotalInclTax());
            $this->_rawRequest = $r;

            return $this;
        }

        /**
         * Set free method request
         *
         * @param string $freeMethod
         * @return null
         */
        protected function _setFreeMethodRequest($freeMethod)
        {
            $r = $this->_rawRequest;
            $weight = $this->getTotalNumOfBoxes($r->getFreeMethodWeight());
            $weight = $this->_getCorrectWeight($weight);
            $r->setWeight($weight);
            $r->setAction($this->getCode('action', 'single'));
            $r->setProduct($freeMethod);
        }


        /**
         * Do shipment request to carrier web service, obtain Print Shipping Labels and process errors in response
         *
         * @param Varien_Object $request
         * @return Varien_Object
         */
        protected function _doShipmentRequest(Varien_Object $request)
        {

        }

        /**
         * Get correct weigt.
         *
         * Namely:
         * Checks the current weight to comply with the minimum weight standards set by the carrier.
         * Then strictly rounds the weight up until the first significant digit after the decimal point.
         *
         * @param float|integer|double $weight
         * @return float
         */
        protected function _getCorrectWeight($weight)
        {
           /* $minWeight = $this->getConfigData('min_package_weight');
            if($weight < $minWeight){
                $weight = $minWeight;
            }*/
            //rounds a number to one significant figure
            $weight = ceil($weight*10) / 10;
            return $weight;
        }

        /**
         * Get tracking
         *
         * @param mixed $trackings
         * @return mixed
         */
        public function getTracking($trackings)
        {
            $return = array();
            if (!is_array($trackings)) {
                $trackings = array($trackings);
            }

            return $this->_getTracking($trackings);
        }

        /**
         * @param $to_address
         * @param $from_address
         * @param $parcel
         * @return mixed
         */
        private function _createShipment($to_address, $from_address, $parcel)
        {
            $shipment = \EasyPost\Shipment::create(
                array(
                    "to_address" => $to_address,
                    "from_address" => $from_address,
                    "parcel" => $parcel
                )
            );
            return $shipment;
        }
    }
