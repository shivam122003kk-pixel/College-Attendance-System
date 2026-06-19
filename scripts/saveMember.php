<?php 
error_reporting(0);
include '../Includes/dbcon.php';

function showNoticeAndRedirect($message, $type, $location) {
    $messageJson = json_encode($message);
    $typeJson = json_encode($type);
    $locationJson = json_encode($location);
    echo "<script src=\"../js/pimt-alerts.js\"></script>";
    echo "<script>
        PIMTAlert.show($messageJson, $typeJson);
        setTimeout(function () { window.location = $locationJson; }, 1400);
    </script>";
    exit;
}

function showNotice($message, $type) {
    $messageJson = json_encode($message);
    $typeJson = json_encode($type);
    echo "<script src=\"../js/pimt-alerts.js\"></script>";
    echo "<script>PIMTAlert.show($messageJson, $typeJson);</script>";
}


    if(isset($_POST['submit'])){

        $firstName = $_POST['firstName'];
        $lastName = $_POST['lastName'];
        $email = $_POST['email'];
        $phoneNo = $_POST['phoneNo'];
        $password = $_POST['password'];
        $conPassword = $_POST['conPassword'];
        $gender = $_POST['gender'];
        $dob = $_POST['dob'];
        $state = $_POST['state'];
        $city = $_POST['city'];
        $address = $_POST['address'];
        $lga = $_POST['lga'];
        $coopAccountId = $_POST['coopAccountId'];
        $userType = $_POST['userType'];
        $dateCreated =  date("Y-m-d");


// echo $isLinked;
// echo $_POST['isExisting'];


        $query = "SELECT * FROM users WHERE emailAddress = '$email'";
        $rs = $conn->query($query);
        $num = $rs->num_rows;

        if($password != $conPassword)
        {
            showNoticeAndRedirect("Password Mismatch!", "warning", "../memberSetup.php");
        }
        
        else if($num > 0)
        {
            showNoticeAndRedirect("Email Address has already been used!", "warning", "../memberSetup.php");

        }
        else
        {
            
            if($_POST['userType'] == 1) // if the userType is staff, save staff info and user info
            {
                $companyId = $_POST['companyId'];
                $staffCode = $_POST['staffCode'];
                $position = $_POST['position'];
                $level = $_POST['level'];
                $department = $_POST['department'];
                $description = $_POST['description'];

                $userqr = "INSERT INTO users (roleId,coopId,firstName,lastName,gender,dob,city,state,lga,emailAddress,address,phoneNo,password,dateCreated) 
                        VALUES ('2','$coopAccountId','$firstName','$lastName','$gender','$dob','$city','$state','$lga','$email','$address','$phoneNo','$password','$dateCreated')";
                $useres = $conn->query($userqr);

                if($useres === TRUE)
                {
                    $qryss = "SELECT * FROM companystaff WHERE compId = '$companyId' AND staffCode = '$staffCode'";
                    $rst = $conn->query($qryss);
                    $num = $rst->num_rows;

                    if($num == 0)
                    {
                        $querys = "SELECT * FROM users WHERE emailAddress = '$email'";
                        $rslt = $conn->query($querys);
                        // $num = $rslt->num_rows;
                        $rrw = $rslt->fetch_assoc();
                        $memberId = $rrw['Id'];

                        $compqr = "INSERT INTO companystaff (staffCode,memberId,compId,coopId,position,level,department,jobDescription,dateCreated) 
                            VALUES ('$staffCode','$memberId','$companyId','$coopAccountId','$position','$level','$department','$description','$dateCreated')";
                        $compres = $conn->query($compqr);

                            if($compres === TRUE)
                            {
                                
                            }
                            else
                            {
                                showNotice("An Error Occurred!", "error");
                            }

                        showNoticeAndRedirect("Created Successfully!", "success", "../index.php");
                    }
                    else
                    {

                        showNoticeAndRedirect("Staff with staff code already exist!", "warning", "../memberSetup.php");
                    }
                }
                else
                {
                    showNotice("An Error Occurred!", "error");
                }

            }


            else if($_POST['userType'] == 2) // if the userType is ExternalMmber, save the member info to the user table only
            {
                $userqr = "INSERT INTO users (roleId,coopId,firstName,lastName,gender,dob,city,state,lga,emailAddress,address,phoneNo,password,dateCreated) 
                        VALUES ('2','$coopAccountId','$firstName','$lastName','$gender','$dob','$city','$state','$lga','$email','$address','$phoneNo','$password','$dateCreated')";
                $useres = $conn->query($userqr);

                if($useres === TRUE)
                {
                    
                }
                else
                {
                    showNotice("An Error Occurred!", "error");
                }

                showNoticeAndRedirect("Created Successfully!", "success", "../index.php");
            }

        } // end of else statement
        
    } //end of if for submit button



        ?>


