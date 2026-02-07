<?php
// Connect to the database

use FontLib\Table\Type\head;

include 'config/config.php';

session_start();

// Handle password change success/error messages FIRST before redirect check
if(isset($_SESSION['success_message']) || isset($_SESSION['error_message'])){
   // Don't redirect to dashboard if we have messages to show
   // This will be handled after showing the messages
} else {
   // Only check for redirect if there are no messages to show
   if(isset($_SESSION['user_type'])){
      header('location: dashboard/');
      exit();
   }
}

echo '<script src="https://kit.fontawesome.com/30b908cc5a.js" crossorigin="anonymous"></script>';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
echo '<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>';

// Handle password change success/error messages FIRST before login processing
if(isset($_SESSION['success_message'])){
   echo "<script>
            window.onload = function() {
               Swal.fire({
                  title: 'Success!',
                  text: '".$_SESSION['success_message']."',
                  icon: 'success',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  allowEnterKey: false,
                  showConfirmButton: true
               }).then((result) => {
                  if (result.isConfirmed) {
                     // Clear session and redirect to login page
                     fetch('logout.php', {
                        method: 'POST'
                     }).then(() => {
                        window.location.href = 'login_form.php';
                     });
                  }
               });
            }
         </script>";
   unset($_SESSION['success_message']);
   // Clear user session data to prevent auto-redirect
   unset($_SESSION['user_type']);
   unset($_SESSION['user_name']);
   unset($_SESSION['user_email']);
   unset($_SESSION['admin_name']);
   unset($_SESSION['admin_email']);
} 
elseif(isset($_SESSION['error_message'])){
   echo "<script>
            window.onload = function() {
               Swal.fire({
                  title: 'Error!',
                  text: '".$_SESSION['error_message']."',
                  icon: 'error',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  allowEnterKey: false,
                  showConfirmButton: true
               }).then((result) => {
                  if (result.isConfirmed) {
                     // Clear session and redirect to login page
                     fetch('logout.php', {
                        method: 'POST'
                     }).then(() => {
                        window.location.href = 'login_form.php';
                     });
                  }
               });
            }
         </script>";
   unset($_SESSION['error_message']);
   // Clear user session data to prevent auto-redirect
   unset($_SESSION['user_type']);
   unset($_SESSION['user_name']);
   unset($_SESSION['user_email']);
   unset($_SESSION['admin_name']);
   unset($_SESSION['admin_email']);
} 
elseif(isset($_POST['submit'])){
   $email = mysqli_real_escape_string($conn, $_POST['email']);
   $pass = md5($_POST['password']);

   $current_day_and_time = date('Y-m-d H:i:s');
   $loginquery = "UPDATE mldb.user_form SET last_online = '$current_day_and_time' WHERE email = '$email'";
   $select = "SELECT * FROM mldb.user_form WHERE email = '$email' && password = '$pass'";
   $result = mysqli_query($conn, $select);
   // Get the current day and time.
   if(mysqli_num_rows($result) > 0){
      $row = mysqli_fetch_array($result);
      if($row['user_type'] == 'admin'){
         if($row['status'] == 'Inactive'){
            echo "<script>
                     window.onload = function() {
                        Swal.fire({
                           title: 'End-User is Inactive',
                           text: 'Please contact the system administrator.',
                           icon: 'error',
                           allowOutsideClick: false,
                           allowEscapeKey: false,
                           allowEnterKey: false,
                           showConfirmButton: true
                        });
                     }
                  </script>";
         }else{
            $loginresult = mysqli_query($conn, $loginquery);
            $_SESSION['admin_name'] =  $row['first_name'].' '.$row['middle_name'].' '.$row['last_name'];
            $_SESSION['admin_email'] = $row['email'];
            $_SESSION['user_type'] = $row['user_type'];
            // $_SESSION['user_roles'] = $row['roles'];
            echo "<script>
                  window.onload = function() {
                     const Toast = Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000,
                        backdrop: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        timerProgressBar: true,
                        didOpen: (toast) => {
                          toast.addEventListener('mouseenter', Swal.stopTimer)
                          toast.addEventListener('mouseleave', Swal.resumeTimer)
                        }
                      })
                      
                      Toast.fire({
                        icon: 'success',
                        title: 'Signed in successfully'
                      }).then(() => {
                        // Redirect to the generate_payment.php page.
                        window.location.href = 'dashboard/';
                    });
                  }
               </script>";
         }
      }elseif($row['user_type'] == 'user'){
         if($row['status'] == 'Inactive'){
            echo "<script>
                     window.onload = function() {
                        Swal.fire({
                           title: 'End-User is Inactive',
                           text: 'Please contact the system administrator.',
                           icon: 'error',
                           allowOutsideClick: false,
                           allowEscapeKey: false,
                           allowEnterKey: false,
                           allowOutsideClick: false,
                           showConfirmButton: true
                        });
                     }
                  </script>";
         }else{
            $loginresult = mysqli_query($conn, $loginquery);
            $_SESSION['user_name'] =  $row['first_name'].' '.$row['middle_name'].' '.$row['last_name'];
            $_SESSION['user_email'] = $row['email'];
            $_SESSION['user_type'] = $row['user_type'];
            // Check if the password is "Password1"
            if($pass == md5("Mlinc1234")){
               // Show a modal to prompt the user to create another password
               echo '<script>
                  window.onload = function() {
                     Swal.fire({
                        title: "Change Password",
                        icon: "warning",
                        showCancelButton: true,
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        allowEnterKey: false,
                        confirmButtonText: "OK",
                        cancelButtonText: "Cancel"
                     }).then((result) => {
                        if (result.isConfirmed) {
                           var changePasswordModal = document.getElementById("changePasswordModal");
                           changePasswordModal.style.display = "block";
                        } 
                        else {
                           // Send AJAX request to destroy session and redirect
                           fetch("logout.php", {
                              method: "POST"
                           }).then(() => {
                              window.location.href = "login_form.php";
                           });
                        }  
                     });
                  }
               </script>';
            } else {
               // Show a Sweetalert mixin with the success message
               if ($_SESSION['user_email'] === 'pera94005055') {
                  echo '<script>
                     window.onload = function() {
                        const Toast = Swal.mixin({
                           toast: true,
                           position: "top-end",
                           showConfirmButton: false,
                           timer: 2000,
                           backdrop: true,
                           allowOutsideClick: false,
                           allowEscapeKey: false,
                           allowEnterKey: false,
                           timerProgressBar: true,
                           didOpen: (toast) => {
                              toast.addEventListener("mouseenter", Swal.stopTimer)
                              toast.addEventListener("mouseleave", Swal.resumeTimer)
                           }
                           });
                           
                           Toast.fire({
                           icon: "success",
                           title: "Signed in successfully",
                           }).then(() => {
                           window.location.href = "dashboard/billspayment-soa/approval/soa-approval.php";
                        });
                     }
                  </script>';
               }
               elseif ($_SESSION['user_email'] === 'cill17098209') {
                  echo '<script>
                     window.onload = function() {
                        const Toast = Swal.mixin({
                           toast: true,
                           position: "top-end",
                           showConfirmButton: false,
                           timer: 2000,
                           backdrop: true,
                           allowOutsideClick: false,
                           allowEscapeKey: false,
                           allowEnterKey: false,
                           timerProgressBar: true,
                           didOpen: (toast) => {
                              toast.addEventListener("mouseenter", Swal.stopTimer)
                              toast.addEventListener("mouseleave", Swal.resumeTimer)
                           }
                           });
                           
                           Toast.fire({
                           icon: "success",
                           title: "Signed in successfully",
                           }).then(() => {
                           window.location.href = "dashboard/billspayment-soa/review/for-checking-review.php";
                        });
                     }
                  </script>';
               }else{
                  echo '<script>
                     window.onload = function() {
                        const Toast = Swal.mixin({
                           toast: true,
                           position: "top-end",
                           showConfirmButton: false,
                           timer: 2000,
                           backdrop: true,
                           allowOutsideClick: false,
                           allowEscapeKey: false,
                           allowEnterKey: false,
                           timerProgressBar: true,
                           didOpen: (toast) => {
                              toast.addEventListener("mouseenter", Swal.stopTimer)
                              toast.addEventListener("mouseleave", Swal.resumeTimer)
                           }
                           });
                           
                           Toast.fire({
                           icon: "success",
                           title: "Signed in successfully",
                           }).then(() => {
                           window.location.href = "dashboard/";
                        });
                     }
                  </script>';
               }
            }
         }
      }
   }else{
      echo '<script>
               window.onload = function() {
                  Swal.fire({
                     title: "Incorrect Username or Password",
                     text: "Please check your username and password. Try again.",
                     icon: "error",
                     allowOutsideClick: false,
                     allowEscapeKey: false,
                     allowEnterKey: false,
                     showConfirmButton: true
                  });
               }
            </script>';
   }
}

// Remove the duplicate session message handling code at the bottom
// Clear the session variables after displaying the modal
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Add this code to handle password change success/error messages
if(isset($_SESSION['success_message'])){
   echo "<script>
            window.onload = function() {
               Swal.fire({
                  title: 'Success!',
                  text: '".$_SESSION['success_message']."',
                  icon: 'success',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  allowEnterKey: false,
                  showConfirmButton: true
               });
            }
         </script>";
   unset($_SESSION['success_message']);
}

if(isset($_SESSION['error_message'])){
   echo "<script>
            window.onload = function() {
               Swal.fire({
                  title: 'Error!',
                  text: '".$_SESSION['error_message']."',
                  icon: 'error',
                  allowOutsideClick: false,
                  allowEscapeKey: false,
                  allowEnterKey: false,
                  showConfirmButton: true
               });
            }
         </script>";
   unset($_SESSION['error_message']);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
   <meta charset="UTF-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>Login Page - ML Billpayment</title>
   <link rel="icon" href="images/MLW logo.png" type="image/png">
   <!-- custom css file link  -->
   <link rel="stylesheet" href="./assets/css/style.css?v=<?php echo time(); ?>">
   <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.4/dist/sweetalert2.min.css">
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.1.4/dist/sweetalert2.all.min.js"></script>
</head>
<body> 
   <!-- Modal for changing password -->
<div id="changePasswordModal" class="change-password-modal">
   <div class="change-password-modal-content">
      <center>
      <h3>Create a New Password</h3>
      <h6 style="font-style:italic; color:red;">(Press ESC to CLOSE)</h6>
      </center>
      <br>
      <form action="change_password.php" method="post">
         <div class="input-container">
            <input type="password" name="new_password" required>
            <label>New Password</label>
         </div>
         <div class="input-container">
            <input type="password" name="confirm_password" required>
            <label>Confirm Password</label>
         </div>
         <center>
         <button type="submit" name="newPass">Change Password</button>
         </center>
      </form>
   </div>
</div>

   <div class="form-container">
      <form action="" method="post">
         <div class="logo">
            <img src="./images/MLW Logo.png" alt="logo">
         </div>
         <h3>ML Billspayment</h3>
         <input type="text" name="email" required placeholder="Enter your username" autocomplete="off" value="<?php echo isset($_POST['email']) ? $_POST['email'] : '';?>" oninput="this.value = this.value.toUpperCase()" required>
         <input type="password" name="password" required placeholder="Enter your password" autocomplete="off" required>
         <input type="submit" name="submit" value="LOGIN" class="form-btn"><br>
         <a href="index.php" style="text-decoration:underline; color:#000;">Back to home</a>
      </form>
   </div>
   <script>
  // Get the modal element
  var modal = document.getElementById("changePasswordModal");

  // Function to close the modal
  function closeModal() {
    modal.style.display = "none";
  }

  // Listen for the ESC key press event
  document.addEventListener("keydown", function(event) {
    if (event.keyCode === 27) {
      closeModal();
    }
  });
</script>
</body>
</html>