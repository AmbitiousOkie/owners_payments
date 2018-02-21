function sendPayment(booking_id){

  xmlhttp = new XMLHttpRequest();
  xmlhttp.open("GET","/wp-content/plugins/papakea-owner-payments/papakea-paypal.php?id="+booking_id,true);
  xmlhttp.send();

  document.getElementById("ajax_notify_class").classList.remove('notification_div_hidden','notification_div_red','notification_div_green');
  document.getElementById("ajax_notify_class").classList.add('notification_div_orange');
  document.getElementById("ajax_notify").innerHTML = 'Waiting on Paypal...';

  console.log(xmlhttp);
  xmlhttp.onreadystatechange = function(){
    if (this.readyState == 4 && this.status == 200) {
      if (this.responseText.includes("PENDING") || this.responseText.includes("EXISTS")){
        // Update notification DIV
        document.getElementById("ajax_notify").innerHTML = 'Payment Sent';
        document.getElementById("ajax_notify_class").classList.remove('notification_div_hidden','notification_div_red','notification_div_orange');
        document.getElementById("ajax_notify_class").classList.add('notification_div_green');

        // Updates column item
        document.getElementById(booking_id).outerHTML = '<span>Paid</span>';
        // location.reload();

      } else {
        // Update notification DIV
        document.getElementById("ajax_notify").innerHTML = 'Payment Failed';
        document.getElementById("ajax_notify_class").classList.remove('notification_div_hidden','notification_div_green','notification_div_orange');
        document.getElementById("ajax_notify_class").classList.add('notification_div_red');
      }

      console.log(this.responseText);
    }
  }

}
