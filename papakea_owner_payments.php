<?php
/*
 * Plugin Name: Papakea list owner payments
 * Description: Displays owner payment details
 * Plugin URI: http://stellarwinds.io
 * Author: Kris Wall
 * Author URI: http://stellarwinds.io
 * Version: 1.0
 * License: GPL2
 */


if(is_admin())
{
    // Prepares the page by enqueueing the scripts, creating the nonce,
    function prepare_owner_payment_page() {

      if(current_user_can('manage_options')) {

        wp_enqueue_script( 'papakea-ajax', plugin_dir_url( __FILE__ ).'papakea-ajax.js', false );
        add_action('wp_enqueue_scripts', 'papakea-ajax');

        // Sets up the nonce and username
        $nonce = wp_create_nonce('owner_payments_nonce');
        $nonce_user = wp_get_current_user()->display_name;

        // Sets the cookie
        setcookie( 'papakea_nonce', $nonce, time()+3600, COOKIEPATH, COOKIE_DOMAIN, TRUE, TRUE );

        // Check SQL for nonce
        global $wpdb;
        $sql_check_nonce = 'SELECT nonce FROM papakea_nonce WHERE nonce = "'.$nonce.'" LIMIT 1';

        if (!$wpdb->get_var($sql_check_nonce)){
          // echo '<script>alert("added to nonce table")</script>';
          $sql_command = "INSERT INTO papakea_nonce (user, nonce) VALUES (%s,%s)";
          $sql_set_nonce = $wpdb->prepare($sql_command, $nonce_user, $nonce);
          $wpdb->query($sql_set_nonce);
        }

      } else {
        return;
      }

      new papakea_list_owner_payments();
    }

    add_action( 'wp_loaded', 'prepare_owner_payment_page' );

}

/**
 * papakea_list_owner_payments class will create the page to load the table
 */
class papakea_list_owner_payments
{
    /**
     * Constructor will create the menu item
     */
    public function __construct()
    {
        add_action( 'admin_menu', array($this, 'add_menu_list_owner_payments' ));
    }

    /**
     * Menu item will allow us to load the page to display the table
     */
    public function add_menu_list_owner_payments()
    {
        add_menu_page( 'Owner Payments', 'Owner Payments', 'manage_options', 'owner-payments.php', array($this, 'papakea_owner_payments_page'), 'dashicons-admin-network' );
    }

    /**
     * Display the list table page
     *
     * @return Void
     */
    public function papakea_owner_payments_page()
    {
        $ownerPaymentsTable = new Owner_Payments_Table();
        $ownerPaymentsTable->prepare_items();

        ?>






            <div class="wrap">
                <div id="icon-users" class="icon32"></div>
                <h2>Owner Payments Page</h2>
                <div id="ajax_notify_class" class="notification_div_hidden">
                  <span id="ajax_notify">Unsent</span>
                </div>
                <?php $ownerPaymentsTable->display(); ?>
                <div><br>
                </div>
            </div>
        <?php
    }
}

// WP_List_Table is not loaded automatically so we need to load it in our application
if( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}







/**
 * Create a new table class that will extend the WP_List_Table
 */
class Owner_Payments_Table extends WP_List_Table
{
    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();

        $data = $this->table_data();
        usort( $data, array( &$this, 'sort_data' ) );

        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );

        $data = array_slice($data,(($currentPage-1)*$perPage),$perPage);

        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Override the parent columns method. Defines the columns to use in your listing table
     *
     * @return Array
     */
    public function get_columns()
    {
        $columns = array(
            'id'          => 'Booking ID',
            'owner'          => 'Owner',
            // 'traveler'       => 'Traveler',
            'date_confirmed' => 'Date Created',
            'property'        => 'Property',
            'status'    => 'Booking Status',
            'cost'      => 'Cost',
            'fee'       => 'Service Fee',
						'paypal_service_fee' => 'Paypal Fee',
						'owner_paid_status' => 'Owner Paid',
						'pay_owner_amount' => 'Action',
        );

        return $columns;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns()
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns()
    {
        return array(
        'id' => array('id', false),
        'owner' => array('owner', false),
        // 'traveler' => array('traveler', false),
        'date_confirmed' => array('date_confirmed', false),
        'property' => array('property', false),
        'status' => array('status', false),
        'cost' => array('cost', false),
        'fee' => array('fee', false),
				'paypal_service_fee' => array('paypal_service_fee', false),
				'owner_paid_status' => array('owner_paid_status', false));
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data()
    {

      // Creates a SQL connection
      global $wpdb;

        $sql = "SELECT p.ID as id
            , p.post_modified as date_confirmed


              , MAX(CASE WHEN m.meta_key = 'owner_id'
                        THEN m.meta_value
                        ELSE NULL END)  AS owner
              , MAX(CASE WHEN m.meta_key = 'booking_id'
                        THEN m.meta_value
                        ELSE NULL END)  AS property
              , MAX(CASE WHEN m.meta_key = 'booking_status'
                        THEN m.meta_value
                        ELSE NULL END)  AS status
              , MAX(CASE WHEN m.meta_key = 'to_be_paid'
                        THEN m.meta_value
                        ELSE NULL END)  AS cost
              , MAX(CASE WHEN m.meta_key = 'service_fee'
                       THEN m.meta_value
                       ELSE NULL END)  AS fee
              , MAX(CASE WHEN m.meta_key = 'owner_paid_status'
                       THEN m.meta_value
                       ELSE NULL END)  AS owner_paid_status



          FROM wp_posts AS p
        INNER JOIN wp_postmeta AS m
            ON m.post_id = p.ID
         WHERE p.post_type = 'wpestate_booking'
        GROUP BY p.ID, p.post_author
        ORDER BY p.ID DESC";


        $sql_result = $wpdb->get_results( $sql, 'ARRAY_A' );


				foreach ($sql_result as $key => $value){



          $pp_ID = $sql_result[$key]['owner'];

          // Sets Paypal owner address
					$sql_paypal_address = 'SELECT meta_value FROM wp_usermeta WHERE user_id = '.$pp_ID.' AND meta_key = "paypal_payments_to" ';
					$sql_paypal_address_results = $wpdb->get_results( $sql_paypal_address );
          $sql_result[$key]['paypal_address'] = $sql_paypal_address_results[0]->meta_value;





          //  Sets the fees
					if($value['cost']) {
            // Paypal fee is 2.9% + $0.30
						$paypal_service_fee = number_format((($value['cost'] * .029) + .30 + .25),2,'.','');
						$sql_result[$key]['paypal_service_fee'] = $paypal_service_fee;

            // Sets the amount to pay to the owner
						$pay_owner_amount = $value['cost']-$value['fee']-$sql_result[$key]['paypal_service_fee'];
						$sql_result[$key]['pay_owner_amount'] = number_format($pay_owner_amount,2,'.','');





					}


					// Checks to see if owner_paid_status has been set in the DB
					if(!$value['owner_paid_status']) {
						// Sets value if not found
						$default_owner_status = 'Unpaid';
						// Updates local array
						$sql_result[$key]['owner_paid_status'] = $default_owner_status;
						// Adds to database
						$wpdb->insert(
							'wp_postmeta',
							array(
								'post_id' => $value['id'],
								'meta_key' => 'owner_paid_status',
								'meta_value' => $default_owner_status
							));
					}


          // Sets the paypal e-mail address and payment amount in the DB
          //Checks if there's an unpaid confirmation
          if($value['status'] == 'confirmed' && $value['owner_paid_status'] ==  'Unpaid') {

            $post_id = $value['id'];
            $pp_address_key = 'paypal_address';
            $pp_address_value = $sql_result[$key]['paypal_address'];
            $pp_payment_key = 'owner_payment_amount';
            $pp_payment_value = $sql_result[$key]['pay_owner_amount'];


            // Add payment address to DB
            $sql_command_search_pp = "SELECT meta_value FROM wp_postmeta WHERE post_id = $post_id AND meta_key='$pp_address_key'";

            if (!$wpdb->get_var($sql_command_search_pp)){
              $sql_command = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE meta_value = meta_value";
              $sql_set_address = $wpdb->prepare($sql_command, $post_id, $pp_address_key, $pp_address_value);
              $wpdb->query($sql_set_address);
            }



            // Add payment amount to DB
            $sql_command_search_pp = "SELECT meta_value FROM wp_postmeta WHERE post_id = $post_id AND meta_key='$pp_payment_key'";

            if (!$wpdb->get_var($sql_command_search_pp)){
              $sql_command = "INSERT INTO wp_postmeta (post_id,meta_key,meta_value) VALUES (%d,%s,%s) ON DUPLICATE KEY UPDATE meta_value = meta_value";
              $sql_set_pay_amount = $wpdb->prepare($sql_command, $post_id, $pp_payment_key, $pp_payment_value);
              $wpdb->query($sql_set_pay_amount);
            }

          }

					}



        return $sql_result;
    }






    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {




        switch( $column_name ) {
            case 'id':
              if (!$item[$column_name]) {
                echo 'Broken';
                return;
              } else {
                // /wp-admin/post.php?post=3708&action=edit
                echo '<a href="/wp-admin/post.php?post='.$item[$column_name].'&action=edit">'.$item[$column_name].'</a>';
                return;
              }

            case 'owner':
              // $user_info = get_user_by("ID", "$item[$column_name]");
							$user_info = get_userdata($item[$column_name]);
              if (!empty($user_info)){
                // echo $user_info->display_name;
                echo '<a href="/wp-admin/user-edit.php?user_id='.$item[$column_name].'">'.$user_info->first_name.' '.$user_info->last_name.'</a>';
                return;
              } else {
                return print_r($item[$column_name]);
              }

            case 'date_confirmed':
            case 'property':
              $property = get_post($item[$column_name]);
              if (!empty($property)) {
                echo '<a href="/properties/'.$property->post_title.'">'.$property->post_title.'</a>';
                return;
              } else {
                return $item[$column_name];
              }
            case 'status':
              if (!$item[$column_name]) {
                echo 'Pending';
                return;
              } elseif ($item[$column_name] == 'pending') {
                echo 'Requested by Traveler';
                return;
              } elseif(!$item['paypal_address']) {
                  echo 'No email address on file';
                  return;
              } elseif($item[$column_name] == 'waiting') {
                echo 'Approved by Owner';
                return;
              }elseif ($item[$column_name] == 'confirmed'){
                echo 'Confirmed by Traveler';
                return;
              }
            case 'cost':
              if(!$item[$column_name]){
                return;
              } else {
                echo '$'.number_format($item[$column_name], 2, '.', '');
                return;
              }
            case 'fee':
              if(!$item[$column_name]){
                return;
              } else {
                echo '$'.number_format($item[$column_name], 2, '.', '');
                return;
              }
						case 'paypal_service_fee':
							if (!$item[$column_name]) {
								return;
							} else {
								echo '$'.number_format($item[$column_name], 2, '.', '');
								return;
							}
						case 'owner_paid_status':
              if (!$item[$column_name]) {
                echo '<div id="owner_paid_status">Unpaid</div>';
                return;
              } else {
                print_r($item[$column_name]);
                return;
              }

						case 'pay_owner_amount':
							if (!$item[$column_name]) {
								return;
							} elseif ($item['owner_paid_status'] == 'Paid') {
                echo 'Paid';
                return;
              } elseif ($item['status'] == 'waiting') {
                return;
              } else {
                $booking_id = $item['id'];
                $pp_address = $item['paypal_address'];
                $pp_amount = $item['pay_owner_amount'];
                // Quotes are problematic
								echo '<span id=\''.$booking_id.'\' onclick=\'sendPayment('.json_encode($booking_id).')\' class="pay_owner_link">Pay the owner $'.number_format($item[$column_name], 2, '.', '').'</span>';
								return;
							}

            // default:
            //     return print_r( $item, true ) ;
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b )
    {
        // Set defaults
        $orderby = 'id';
        $order = 'des';

        // If orderby is set, use this as the sort column
        if(!empty($_GET['orderby']))
        {
            $orderby = $_GET['orderby'];
        }

        // If order is set use this as the order
        if(!empty($_GET['order']))
        {
            $order = $_GET['order'];
        }


        $result = strcmp( $a[$orderby], $b[$orderby] );

        if($order === 'asc')
        {
            return $result;
        }

        return -$result;
    }
}
?>
