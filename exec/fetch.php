<?php
    include "../includes/db.php";


    if(isset($_POST['action'])){

        if ($_POST['action'] == 'login') {   
            if (isset($_POST['username']) && isset($_POST['password'])) {
                $username = $_POST['username'];
                $password = $_POST['password'];
        
                $query = "SELECT Emp_Acc_Id, Emp_Id, Username, Password, User_lvl_Id 
                          FROM eims.accounts 
                          WHERE Username = ? AND Status = 1";
        
                // Prepare the statement to prevent SQL injection
                $params = array($username);
                $stmt = sqlsrv_prepare($con2, $query, $params);
        
                if (!$stmt) {
                    die(print_r(sqlsrv_errors(), true));
                }
        
                if (sqlsrv_execute($stmt)) {
                    if (sqlsrv_has_rows($stmt)) {
                        while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
                            $db_user_Id = $row['Emp_Acc_Id'];
                            $db_emp_Id = $row['Emp_Id'];
                            $db_username = $row['Username'];
                            $db_user_password = $row['Password'];
                            $db_user_lvl = $row['User_lvl_Id'];
                        }
        
                        if (isset($db_user_password)) {
                            if (password_verify($password, $db_user_password)) {
                                setcookie("EIMS_usr_Id", $db_user_Id, time() + 3600 * 24 * 365, '/');
                                setcookie("EIMS_emp_Id", $db_emp_Id, time() + 3600 * 24 * 365, '/');
                                setcookie("EIMS_usrname", $db_username, time() + 3600 * 24 * 365, '/');
                                setcookie("EIMS_usrlvl", $db_user_lvl, time() + 3600 * 24 * 365, '/');
        
                                echo json_encode('1'); // Successful login
                            } else {
                                echo json_encode('2'); // Password mismatch
                            }
                        } else {
                            echo json_encode('2'); // Password not found
                        }
                    } else {
                        echo json_encode('4'); // User not found or inactive
                    }
                } else {
                    die(print_r(sqlsrv_errors(), true));
                }
            } else {
                echo json_encode('3'); // Missing username or password
            }
        }
        


    }//isset action


?>