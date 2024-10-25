// edit_profile.js
$(document).ready(function() {
    $('#editProfileLink').on('click', function(e) {
        e.preventDefault();
        showPasswordModal();
    });

    function showPasswordModal() {
        const modalHTML = `
        <div class="modal fade" id="passwordVerificationModal" tabindex="-1" role="dialog" aria-labelledby="passwordVerificationModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="passwordVerificationModalLabel">Verify Password</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <p>You're about to view and edit your account. Please enter your password to confirm:</p>
                        <form id="passwordVerificationForm">
                            <div class="form-group">
                                <input type="password" class="form-control" id="verificationPassword" maxlength="20" required>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="verifyPasswordBtn">Verify</button>
                    </div>
                </div>
            </div>
        </div>
        `;

        // Append modal to body
        $('body').append(modalHTML);

        // Show the modal
        $('#passwordVerificationModal').modal('show');

        // Handle verify button click
        $('#verifyPasswordBtn').on('click', function() {
            const password = $('#verificationPassword').val().substring(0, 20);
            if (password) {
                verifyPassword(password);
            }
        });

        // Enforce 20-character limit
        $('#verificationPassword').on('input', function() {
            if ($(this).val().length > 20) {
                $(this).val($(this).val().substring(0, 20));
            }
        });

        // Remove modal from DOM when hidden
        $('#passwordVerificationModal').on('hidden.bs.modal', function () {
            $(this).remove();
        });
    }

    function verifyPassword(password) {
        $.ajax({
            url: 'verify_admin_password.php',
            type: 'POST',
            data: { password: password },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#passwordVerificationModal').modal('hide');
                    fetchUserData();
                } else {
                    alert('Incorrect password. Please try again.');
                    $('#verificationPassword').val(''); // Clear the password field
                    $('#verificationPassword').focus(); // Set focus back to the password field
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                alert('An error occurred while verifying the password.');
                $('#verificationPassword').val(''); // Clear the password field
                $('#verificationPassword').focus(); // Set focus back to the password field
            }
        });
    }

    function fetchUserData() {
        $.ajax({
            url: 'get_user_data.php',
            type: 'GET',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    fillFormWithUserData(response.userData);
                    $('#editProfileModal').modal('show');
                } else {
                    alert('Error fetching user data: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                alert('An error occurred while fetching user data: ' + error);
            }
        });
    }

    function fillFormWithUserData(userData) {
        $('#user_id').val(userData.user_id);
        $('#user_email').val(userData.user_email);
        $('#user_contact').val(userData.user_contact);
        // Password fields are left empty
    }

    $('#editProfileModal').on('hidden.bs.modal', function () {
        clearForm();
    });

    $('#clearFormButton').on('click', function() {
        clearForm();
    });

    $('#saveProfileChanges').on('click', function() {
        if (validateForm()) {
            var formData = {
                user_id: $('#user_id').val(),
                user_email: $('#user_email').val(),
                user_contact: $('#user_contact').val(),
                user_pass: $('#user_pass').val(),
                user_pass_confirm: $('#user_pass_confirm').val()
            };

            $.ajax({
                url: 'update_profile.php',
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#editProfileModal').modal('hide');
                        alert(response.message);
                        // Reload the page after the alert is dismissed
                        window.location.reload();
                    } else {
                        displayErrors(response.errors);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('An error occurred: ' + error);
                }
            });
        }
    });

    function validateForm() {
        let isValid = true;
        const errors = {};

        // Username validation
        const username = $('#user_id').val().trim().toLowerCase();
        if (username === '') {
            errors.user_id = 'Username is required';
            isValid = false;
        } else if (!/^[a-z0-9]{3,15}$/.test(username)) {
            errors.user_id = 'Username must be 3-15 characters and can only contain lowercase letters and numbers';
            isValid = false;
        }

        // Email validation
        const email = $('#user_email').val().trim();
        if (email === '') {
            errors.user_email = 'Email is required';
            isValid = false;
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            errors.user_email = 'Invalid email format';
            isValid = false;
        }

        // Contact number validation
        const contact = $('#user_contact').val().trim();
        if (contact === '') {
            errors.user_contact = 'Contact number is required';
            isValid = false;
        } else if (!/^\d{11}$/.test(contact)) {
            errors.user_contact = 'Contact number must be 11 digits';
            isValid = false;
        }

        // Password validation (only if a new password is entered)
        const password = $('#user_pass').val();
        const confirmPassword = $('#user_pass_confirm').val();
        if (password !== '') {
            if (password.length < 8 || password.length > 20) {
                errors.user_pass = 'Password must be 8-20 characters long';
                isValid = false;
            } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?])/.test(password)) {
                errors.user_pass = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one symbol';
                isValid = false;
            }

            if (password !== confirmPassword) {
                errors.user_pass_confirm = 'Passwords do not match';
                isValid = false;
            }
        }

        displayErrors(errors);
        return isValid;
    }

    function displayErrors(errors) {
        // Clear previous error messages
        $('.error-message').remove();

        // Display new error messages
        for (const [field, message] of Object.entries(errors)) {
            $(`#${field}`).after(`<div class="error-message text-danger">${message}</div>`);
        }
    }

    function clearForm() {
        $('#editProfileForm')[0].reset();
        $('.error-message').remove();
    }

    // Convert username to lowercase as user types
    $('#user_id').on('input', function() {
        $(this).val($(this).val().toLowerCase());
    });

    // Trim whitespace from inputs
    $('input').on('blur', function() {
        $(this).val($.trim($(this).val()));
    });

    // Limit email to 30 characters
    $('#user_email').on('input', function() {
        if ($(this).val().length > 30) {
            $(this).val($(this).val().substr(0, 30));
        }
    });

    // Ensure contact number only contains digits
    $('#user_contact').on('input', function() {
        $(this).val($(this).val().replace(/[^0-9]/g, ''));
        if ($(this).val().length > 11) {
            $(this).val($(this).val().substr(0, 11));
        }
    });
});
