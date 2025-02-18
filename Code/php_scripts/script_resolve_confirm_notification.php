<?php
session_start();

include 'config.php';

if(isset($_SESSION["username"])){

    $conn = new mysqli($servername_db, $username_db, $password_db, $dbname);

    $new_notification_id = isset($_POST['notify_id']) ? (string)$_POST['notify_id'] : null;
    $username_session_issuer = $_SESSION["username"];

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    $sql_info = "SELECT travel_id, issuer, wallet FROM notifications WHERE notification_id = '$new_notification_id';";

    $travel_id = $conn->query($sql_info);

    $row = $travel_id->fetch_assoc();
    $var_travel_id = $row["travel_id"];
    $var_new_receiver = $row["issuer"];
    $var_passenger_wallet = $row["wallet"];
    
    $currentDateTime = new DateTime();
    $effective_notification_time = $currentDateTime->format('Y-m-d H:i:s');
    $notification_id_hash = (string) hash('sha256', $var_travel_id.$username_session_issuer.$var_new_receiver.$effective_notification_time);
    
    $sql_send_notification_to_passenger = "INSERT INTO remora_db.notifications(notification_id, receiver, issuer, travel_id, type, notification_date, wallet) VALUES ('$notification_id_hash', '$var_new_receiver', '$username_session_issuer', '$var_travel_id', 'INFORMATION_ACCEPTED', '$effective_notification_time', '');";

    $conn->query($sql_send_notification_to_passenger);

    $sql_delete_notification = "DELETE FROM remora_db.notifications WHERE notification_id = '$new_notification_id';";
    
    $conn->query($sql_delete_notification);

    $sql_driver_check_ongoing_trips = "SELECT * FROM remora_db.ongoing_trips WHERE travel_id = '$var_travel_id' AND username = '$username_session_issuer' AND role = 'DRIVER';";

    $result_driver_check = $conn->query($sql_driver_check_ongoing_trips);

    if($result_driver_check->num_rows == 0){
        $sql_driver_update_ongoing_trips_table = "INSERT INTO remora_db.ongoing_trips(travel_id, username, role, wallet, status) VALUES ('$var_travel_id', '$username_session_issuer', 'DRIVER', '', 'NOT_STORED')";
        
        $conn->query($sql_driver_update_ongoing_trips_table);
    }

    $sql_passenger_update_ongoing_trips_table = "INSERT INTO remora_db.ongoing_trips(travel_id, username, role, wallet, status) VALUES ('$var_travel_id', '$var_new_receiver', 'PASSENGER', '$var_passenger_wallet', 'PAY_LOCKED')";
        
    $conn->query($sql_passenger_update_ongoing_trips_table);
    
    $conn->close();

    $response = array('message' => 'Notification_update');
    echo json_encode($response);

} else {
    $response = array('message' => 'You are not logged in :(');
    echo json_encode($response);
}

?>