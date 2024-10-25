$(document).ready(function() {
    // Style for the "Forget your password?" link
    $('.forget-password').css({
        'color': '#6c757d',
        'transition': 'color 0.3s'
    });

    // Hover effect for the "Forget your password?" link
    $('.forget-password').hover(
        function() {
            $(this).css('color', '#219130');
        },
        function() {
            $(this).css('color', '#6c757d');
        }
    );

    // Show modal when clicking "Forget your password?"
    $('.forget-password').click(function(e) {
        e.preventDefault();
        $('#resetPasswordModal').modal('show');
    });

    // Proceed button click handler
    $('#proceedBtn').click(function() {
        var email = $('#resetEmail').val().trim();
        if (email) {
            if (isValidEmail(email)) {
                checkEmailExists(email);
            } else {
                showFadePrompt('Please enter a valid email address.');
            }
        } else {
            showFadePrompt('Please enter an email address.');
        }
    });

    // Function to check if email exists
    function checkEmailExists(email) {
        $.ajax({
            url: 'check_email.php',
            type: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                if (response.exists) {
                    $('#emailInputSection').hide();
                    $('#otpSection').html(`
                        <div class="text-center mb-4">
                            <p>We've found an account associated with this email address.</p>
                            <p>To reset your password, please click the button below. We will send a one-time password (OTP) to your email for verification.</p>
                        </div>
                        <div class="container-login100-form-btn">
                            <button id="sendOtpBtn" class="login100-form-btn">SEND OTP</button>
                        </div>
                    `).show();
                } else {
                    showFadePrompt('No account found with this email address.');
                }
            },
            error: function() {
                showFadePrompt('An error occurred. Please try again.');
            }
        });
    }

    // Function to show a fade prompt
    function showFadePrompt(message, type = 'error') {
        var alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        var prompt = $('<div class="alert ' + alertClass + '">' + message + '</div>')
            .css({
                'position': 'fixed',
                'top': '20px',
                'left': '50%',
                'transform': 'translateX(-50%)',
                'z-index': '9999',
                'opacity': '0'
            })
            .appendTo('body')
            .animate({ opacity: 1 }, 300)
            .delay(3000)
            .animate({ opacity: 0 }, 300, function() {
                $(this).remove();
            });
    }

    // Function to validate email
    function isValidEmail(email) {
        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    // Update the Send OTP button click handler
    $(document).on('click', '#sendOtpBtn', function() {
        var email = $('#resetEmail').val().trim();
        sendOTP(email);
    });

    function sendOTP(email) {
        $.ajax({
            url: 'otpsendpass.php',
            type: 'POST',
            data: { email: email },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showOTPInputForm(email);
                } else {
                    showFadePrompt(response.message || 'Failed to send OTP. Please try again.');
                }
            },
            error: function() {
                showFadePrompt('An error occurred. Please try again.');
            }
        });
    }

    function showOTPInputForm(email) {
        $('#otpSection').html(`
            <div class="text-center mb-4">
                <p>An OTP has been sent to your email. Please enter the 6-digit code to verify:</p>
            </div>
            <div class="wrap-input100 validate-input m-b-10" data-validate="OTP is required">
                <input class="input100" type="text" id="otpInput" placeholder="Enter OTP" required maxlength="6">
                <span class="focus-input100"></span>
                <span class="symbol-input100">
                    <i class="fa fa-key"></i>
                </span>
            </div>
            <div class="container-login100-form-btn">
                <button id="verifyOtpBtn" class="login100-form-btn">VERIFY OTP</button>
            </div>
        `);

        // Store email in a data attribute
        $('#otpSection').data('email', email);
    }

    $(document).on('click', '#verifyOtpBtn', function() {
        var otp = $('#otpInput').val().trim();
        var email = $('#otpSection').data('email');
        if (otp) {
            verifyOTP(email, otp);
        } else {
            showFadePrompt('Please enter the OTP.');
        }
    });

    function verifyOTP(email, otp) {
        $.ajax({
            url: 'verotppass.php',
            type: 'POST',
            data: { email: email, otp: otp },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showNewPasswordForm(email);
                } else {
                    showFadePrompt(response.message || 'Invalid OTP. Please try again.');
                }
            },
            error: function() {
                showFadePrompt('An error occurred. Please try again.');
            }
        });
    }

    function showNewPasswordForm(email) {
        $('#otpSection').html(`
            <div class="text-center mb-4">
                <p>Please enter your new password:</p>
            </div>
            <div class="wrap-input100 validate-input m-b-10" data-validate="New password is required">
                <input class="input100" type="password" id="newPassword" placeholder="Enter New Password" required>
                <span class="focus-input100"></span>
                <span class="symbol-input100">
                    <i class="fa fa-lock"></i>
                </span>
            </div>
            <div class="password-requirements text-muted small mb-3">
                Password must include: 1 uppercase letter, 1 lowercase letter, 1 number, and 1 symbol.
            </div>
            <div class="wrap-input100 validate-input m-b-10" data-validate="Confirm password is required">
                <input class="input100" type="password" id="confirmPassword" placeholder="Confirm New Password" required>
                <span class="focus-input100"></span>
                <span class="symbol-input100">
                    <i class="fa fa-lock"></i>
                </span>
            </div>
            <div class="container-login100-form-btn">
                <button id="resetPasswordBtn" class="login100-form-btn">RESET PASSWORD</button>
            </div>
        `);

        // Store email in a data attribute
        $('#otpSection').data('email', email);
    }

    $(document).on('click', '#resetPasswordBtn', function() {
        var newPassword = $('#newPassword').val();
        var confirmPassword = $('#confirmPassword').val();
        var email = $('#otpSection').data('email');

        if (!validatePassword(newPassword)) {
            showFadePrompt('Password does not meet requirements.');
            return;
        }

        if (newPassword !== confirmPassword) {
            showFadePrompt('Passwords do not match.');
            return;
        }

        resetPassword(email, newPassword);
    });

    function validatePassword(password) {
        return password.length >= 10 && 
               /[A-Z]/.test(password) && 
               /[a-z]/.test(password) && 
               /\d/.test(password) && 
               /[\W_]/.test(password);
    }

    function resetPassword(email, newPassword) {
        $.ajax({
            url: 'reset_password_final.php',
            type: 'POST',
            data: { email: email, new_password: newPassword },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showFadePrompt('Password reset successfully. You can now login with your new password.', 'success');
                    setTimeout(function() {
                        $('#resetPasswordModal').modal('hide');
                    }, 3000);
                } else {
                    showFadePrompt(response.message || 'Failed to reset password. Please try again.');
                }
            },
            error: function() {
                showFadePrompt('An error occurred. Please try again.');
            }
        });
    }
});
