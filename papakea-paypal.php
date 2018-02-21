<?php
  class connection {

    // Set SQL Connection details
    var $servername = '';
    var $username = '';
    var $password = '';
    var $dbname = '';

    // Returns the nonce from SQL
    function returnNonce($value){
      $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      // Searches for the nonce in the database
      $sql = "SELECT nonce FROM papakea_nonce WHERE nonce = '".$value."' LIMIT 1";
      $query = mysqli_query($conn, $sql);
      $table = [];
      while ($row = $query->fetch_assoc()) {
          $table[] = $row;
      }
      $sql_nonce = strval($table[0]['nonce']);
      $conn->close();
      return $sql_nonce;
    }

    // Returns meta_value from SQL
    function returnMetaValue($post_id, $meta_key){
      $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      // Searches for the nonce in the database
      $sql = "SELECT meta_value FROM wp_postmeta WHERE post_id = ".$post_id." AND meta_key = '".$meta_key."' LIMIT 1";
      $query = mysqli_query($conn, $sql);
      $table = [];
      while ($row = $query->fetch_assoc()) {
          $table[] = $row;
      }
      $result = strval($table[0]['meta_value']);
      $conn->close();
      // Returns the field
      return $result;
    }

    // Updates meta_value and results success bool value
    function updateMetaValue($post_id, $meta_key, $meta_value){
      $conn = new mysqli($this->servername, $this->username, $this->password, $this->dbname);
      if ($conn->connect_error) {
          die("Connection failed: " . $conn->connect_error);
      }

      $sql = "UPDATE wp_postmeta SET meta_value = '$meta_value'  WHERE post_id = $post_id AND meta_key = '$meta_key'";
      print_r($sql);

      if ($conn->query($sql) === TRUE) {
          // echo "Record updated successfully";
          $conn->close();
          return true;
      } else {
          echo "Error updating record: " . $conn->error;
          $conn->close();
          return false;
      }
    }

    // Validates the booking_id, cookie, connection details, e-mail, amount
    function validateRequest($ID,$cookie){
      $booking_id = intval($ID);

      // Kills the session if no cookie or booking_id
      if(!isset($cookie) || !$cookie || !isset($booking_id) || !$booking_id){
        die('Not allowed.');
      }

      $sql_nonce = $this->returnNonce($cookie);
      $email = $this->returnMetaValue($booking_id, 'paypal_address');
      $amount = $this->returnMetaValue($booking_id, 'owner_payment_amount');

      // Validates the values in the DB
      if (!$sql_nonce || $sql_nonce != $cookie){
        die('Nonce not found.');
      } else if(!$email) {
        die('E-mail not found.');
      } else if (!$amount) {
        die('Amount not found.');
      }


    }

    // Returns a token from Paypal
    function getToken() {
      // Get the token from Paypal
      $ch_token = curl_init();

    
      $clientId = "";
      $secret = "";

      // Set the CURL headers
      $token_headers = array(
        "Accept: application/json",
        "Accept-Language: en_US"
      );

      // Set the CURL options
      curl_setopt($ch_token, CURLOPT_URL, "https://api.paypal.com/v1/oauth2/token");
      curl_setopt($ch_token, CURLOPT_HTTPHEADER, $token_headers);
      curl_setopt($ch_token, CURLOPT_HEADER, false);
      curl_setopt($ch_token, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch_token, CURLOPT_POST, true);
      curl_setopt($ch_token, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch_token, CURLOPT_USERPWD, $clientId.":".$secret);
      curl_setopt($ch_token, CURLOPT_POSTFIELDS, "grant_type=client_credentials");

      // Execute the CURL command
      $result = curl_exec($ch_token);

      // Quit if no response
      if(empty($result))die("Error: No response.");
      else
      {
          $json = json_decode($result);
          return $json->access_token;
      }

      // Close the connection if no response
      curl_close($ch_token);
    }

    // Returns status of payment
    function sendPayment($token,$booking_id,$email,$amount){
      $ch_payment = curl_init();

      // Set the CURL headers
      $payment_headers = array(
        "Content-Type: application/json",
        "Authorization: Bearer ".$token
      );

      // Set the payment data
      $payment_data = '{
        "sender_batch_header":{
          "sender_batch_id":"'.$booking_id.'",
          "email_subject":"Payment from Papakea Condos",
          "recipient_type":"EMAIL"
        },
        "items":[
          {
            "recipient_type":"EMAIL",
            "amount":{
              "value":"'.$amount.'",
              "currency":"USD"
            },
            "note":"Thank you for renting with PapakeaCondos.com!",
            "sender_item_id":"201403140001",
            "receiver":"'.$email.'"
          }
        ]
      }';

      // Set the CURL options
      curl_setopt($ch_payment, CURLOPT_URL, "https://api.paypal.com/v1/payments/payouts");
      curl_setopt($ch_payment, CURLOPT_POST, true);
      curl_setopt($ch_payment, CURLOPT_HEADER, false);
      curl_setopt($ch_payment, CURLOPT_SSL_VERIFYPEER, true);
      curl_setopt($ch_payment, CURLOPT_HTTPHEADER, $payment_headers);
      curl_setopt($ch_payment, CURLOPT_POSTFIELDS, $payment_data);
      curl_setopt($ch_payment, CURLOPT_RETURNTRANSFER, true);

      // Execute the CURL command
      $payment_result = curl_exec($ch_payment);
      if(empty($payment_result)){
        die("CURL Error: No response.");
      }
      return $payment_result;
    }

    // Checks the response
    function checkStatus($status, $booking_id){
      $status_array = json_decode($status);

      if($status_array->details['0']->issue == 'Batch with given sender_batch_id already exists'){
        echo "EXISTS";
        $this->updateMetaValue($booking_id,'owner_paid_status','Paid' );
      } else if($status_array->batch_header->batch_status == 'PENDING'){
        echo "PENDING";
        $this->updateMetaValue($booking_id,'owner_paid_status','Paid' );
        $this->updateMetaValue($booking_id, $status_array->batch_header->payout_batch_id,'payout_batch_id');
      } else {
        echo "FAILED";
      }
    }

  }






  $paypal = new connection();

  $paypal->validateRequest($_GET['id'],$_COOKIE['papakea_nonce']);
  $email = $paypal->returnMetaValue($_GET['id'], 'paypal_address');
  $amount = $paypal->returnMetaValue($_GET['id'], 'owner_payment_amount');
  $token = $paypal->getToken();
  $status = $paypal->sendPayment($token, $_GET['id'], $email, $amount);
  $paypal->checkStatus($status,$_GET['id']);

 ?>
