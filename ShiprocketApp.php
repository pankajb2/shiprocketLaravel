<?php
namespace App\Library\Services;
use App\Library\Services\ShiprocketAPI;
/**
 * Class ShiprocketApp hits particular shiprocket apis with required credits
 */
class ShiprocketApp
{
    private $_API = array();
    /**
	 * Checks for presence of setup $data array and loads
	 * @param bool $data
	 */
	public function __construct($data = FALSE)
	{
		if (is_array($data))
		{
		      $this->setup($data);
		}

	}
    /**
     * Returns Shiprocket connection, filters provided data, and loads into $this->_API
     * @param array data
     */
    public function setup($data = array())
    {
        $this->_API = new ShiprocketAPI(['EMAIL' => $data['EMAIL'], 'PASSWORD' => $data['PASSWORD'], 'API_URL' =>$data['API_URL']]);
        $this->_API->getAccessToken();
    }

    /**
     * getChannelList The ShipRocket API for getting the data of all integrated channels in the ShipRocket account.
     * @return array result with status and response data/message
     */

    public function getChannelList(){
        $result =array();
        $response = $this->_API->call(['URL'=>'/v1/external/channels', 'METHOD' => 'GET','ALLDATA'=>true,'RETURNARRAY'=>true]);
        if(isset($response['_ERROR'])){
            $result = ['status' => $response['_ERROR']['NUMBER'], 'data' => $response['_ERROR']['MESSAGE']];
        } else if(isset($response['data'])){
            $result = ['status' => 200, 'data' => $response['data']];
        } else {
            $result = ['status' => false, 'data' => 'Channel list not fetched'];
        }
        return $result;
    }

    /**
     * Get a JSON representation of serviceability of courier companies list with pricing. 
     * @param int pickup_postcode required must be 6 digits
     * @param int delivery_postcode required must be 6 digits
     * @param int weight required must be in KG Ex:- 0.50
     * @param int cod required ,if cash on delivery then cod=1 , if prepaid then cod=0
     * @return array result with response status and data
     */

    public function getServiceability($pickup_postcode=NULL,$delivery_postcode=NULL,$weight=NULL,$cod=0){
        if($pickup_postcode && $delivery_postcode && $weight){
            $url = '/v1/external/courier/serviceability?pickup_postcode='.$pickup_postcode.'&delivery_postcode='.$delivery_postcode.'&weight='.$weight.'&cod='.$cod;
            $result = array();
            $response=$this->_API->call(['URL' => $url, 'METHOD' => 'GET','ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($response['status'])){
                $result = ['status' => $response['status'], 'data' => $response['data']];
            } else if(isset($response['status_code'])){
                $result = ['status' => $response['status_code'], 'data' => $response['message']];
            } else if(isset($response['_ERROR'])){
                $result = ['status' => $response['_ERROR']['NUMBER'], 'data' => $response['_ERROR']['MESSAGE']];
            }
            return $result;
        }
        return false;
    }

    /**
     * Get order and shipment details 
     * @param int order_id required
     * @return array result with response status and data
     */

    public function getOrder($order_id=NULL){
        if($order_id){
            $url = '/v1/external/orders/show/'.$order_id;
            $result = array();
            $response=$this->_API->call(['URL' => $url, 'METHOD' => 'GET','ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($response['_ERROR'])){
                $result = ['status' => $response['_ERROR']['NUMBER'], 'data' => $response['_ERROR']['MESSAGE']];
            } else if(isset($response['data'])){
                $result = ['status' => 200, 'data' => $response['data']];
            } else {
                $result = ['status' => false, 'data' => 'Order Not found'];
            }
            return $result;
        }
        return false;
    }

    /**
     * Create Custom order
     * @param array order required
     * @return array result with response status and data
     */

    public function createOrder($order){
        if($order){
            $result = array();
            $response = $this->_API->call(['URL' => '/v1/external/orders/create/adhoc', 'METHOD' => 'POST','DATA'=>$order,'ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($response['order_id'])){
                $result = ['status' => 200, 'data' => $response];
            } else if(isset($response['status_code'])){
                $result = ['status' => $response['status_code'], 'data' => $response['message']];
            } else if(isset($response['_ERROR'])){
                $result = ['status' => $response['_ERROR']['NUMBER'], 'data' => $response['_ERROR']['MESSAGE']];
            }
            return $result;
        }
        return false;
    }

    /**
     * createAwb assign awb single and reassign
     * @param array data required contains shipment id and courier id
     * @return array result with response status and data
     */
    public function createAwb($data){
        if($data){
            $result = array();
            $re = $this->_API->call(['URL' => '/v1/external/courier/assign/awb', 'METHOD' => 'POST','DATA'=>$data,'ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['awb_assign_status'])){
                if($re['awb_assign_status'] == 0){
                    $result = ['status' => 500, 'data' => $re['response']['data']];
                } else if($re['awb_assign_status'] == 1) {
                    $result = ['status' => 200, 'data' => $re['response']['data']];
                }
            } else if(isset($re['status_code'])){
                $result = ['status' => $re['status_code'], 'data' => $re['message']];
            } else {
                $result = ['status' => false, 'data' => 'Unexpected Response'];
            }
            return $result;
        }
        return false;
    }

    /**
     * Get a label URL. Note:- For label URL, AWB code must be assigned on shipment id.
     * @param data array required contains shipment_id
     * @return array result
     */
    public function generateLabel($data){
        if($data){
            $result = array();
            $re = $this->_API->call(['URL'=>'/v1/external/courier/generate/label','METHOD'=>'POST','DATA'=>$data,'ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['label_created'])){
                if($re['label_created'] == 0){
                    $result = ['status' => 500, 'data' => $re['response']];
                } else if($re['label_created'] == 1){
                    $result = ['status' => 200, 'data' => $re['label_url']];
                }
            } else if(isset($re['_ERROR'])){
                $result = ['status' => $re['_ERROR']['NUMBER'], 'data' => $re['_ERROR']['MESSAGE']];
            }  else {
                $result = ['status' => false, 'data' => 'Label not generated'];
            }
            return $result;
        }
        return false;
    }

    /**
     * requestPickup Request for PickUp
     * The API returns the pickup status along with the estimated pickup time
     * @param data array required
     * @return array result
     */
    public function requestPickup($data){
        if($data){
            $result = array();
            $re = $this->_API->call(['URL'=>'/v1/external/courier/generate/pickup','METHOD'=>'POST','DATA'=>$data,'ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['pickup_status'])){
                $result = ['status' => 200, 'data' => $re['response']['data']];
            } else if(isset($re['_ERROR'])){
                $result = ['status' => $re['_ERROR']['NUMBER'], 'data' => $re['_ERROR']['MESSAGE']];
            }   else{
                $result = ['status' => 500, 'data' => 'Request not generated'];
            }
            return $result;
        }
        return false;
    }

    /**
     * getAwbTracking Get a JSON of tracking data by AWB.
     * @param int awb_code required
     * @return array result
     */
    public function getAwbTracking($awb_code=NULL){
        if($awb_code){
            $result = array();
            $re = $this->_API->call(['URL'=>'/v1/external/courier/track/awb/'.$awb_code,'METHOD'=>'GET','ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['tracking_data']['track_status'])){
                $result = ['status' => 500, 'data' => $re['tracking_data']['error']];
            } else if(isset($re['tracking_data']['shipment_track'])){
                    $result = ['status' => 200, 'data' => $re];
            } else if(isset($re['_ERROR'])){
                $result = ['status' => $re['_ERROR']['NUMBER'], 'data' => $re['_ERROR']['MESSAGE']];
            } else{
                $result = ['status' => false, 'data' => 'Tracking not fetched'];
            }
            return $result;
        }
        return false;
    }

    /**
     * getShipmentTracking Get a JSON of tracking data by Shipment.
     * @param int shipment_id required
     * @return array result
     */
    public function getShipmentTracking($shipment_id=NULL){
        if($awb_code){
            $result = array();
            $re = $this->_API->call(['URL'=>'/v1/external/courier/track/shipment/'.$shipment_id,'METHOD'=>'GET','ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['_ERROR'])){
                $result = ['status' => $re['_ERROR']['NUMBER'], 'data' => $re['_ERROR']['MESSAGE']];
            } else if(isset($re['tracking_data']['track_status'])){
                if($re['tracking_data']['track_status'] == 0){
                    $result = ['status' => 500, 'data' => $re['tracking_data']['error']];
                }else if($re['tracking_data']['track_status'] == 1){
                    $result = ['status' => 200, 'data' => $re];
                }
            }else{
                $result = ['status' => false, 'data' => 'Tracking not fetched'];
            }
            return $result;
        }
        return false;
    }

    /**
     * requestManifest Request for PickUp
     * The API returns the manifest url 
     * @param data array required
     * @return array result
     */
    public function requestManifest($data){
        if($data){
            $result = array();
            $re = $this->_API->call(['URL'=>'/v1/external/manifests/generate','METHOD'=>'POST','DATA'=>$data,'ALLDATA'=>true,'RETURNARRAY'=>true]);
            if(isset($re['status']) && $re['status'] == 200){
                $result = ['status' => 200, 'data' => $re['manifest_url']];
            } else if(isset($re['_ERROR'])){
                $result = ['status' => $re['_ERROR']['NUMBER'], 'data' => $re['_ERROR']['MESSAGE']];
            }   else {
                $result = ['status' => 500, 'data' => 'Request not generated'];
            }
            return $result;
        }
        return false;
    }
}
