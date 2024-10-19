// edit_profile.js
$(document).ready(function() {
    $('#editProfileLink').on('click', function(e) {
        e.preventDefault();
        $('#editProfileModal').modal('show');
    });

    $('#editProfileModal').on('hidden.bs.modal', function () {
        clearForm();
    });

    $('#clearFormButton').on('click', function() {
        clearForm();
    });

    $('#saveProfileChanges').on('click', function() {
        if (validateForm()) {
            $.ajax({
                url: 'update_profile.php',
                type: 'POST',
                data: $('#editProfileForm').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('Profile updated successfully');
                        $('#editProfileModal').modal('hide');
                    } else {
                        displayErrors(response.errors);
                    }
                },
                error: function(xhr, status, error) {
                    alert('An error occurred: ' + error);
                }
            });
        }
    });

    function validateForm() {
        let isValid = true;
        const errors = {};

        // Username validation
        const username = $('#user_id').val().trim();
        if (username === '') {
            errors.user_id = 'Username is required';
            isValid = false;
        } else if (!/^[a-zA-Z0-9_]{3,20}$/.test(username)) {
            errors.user_id = 'Username must be 3-20 characters and can only contain letters, numbers, and underscores';
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
        } else if (!/^\d{10,11}$/.test(contact)) {
            errors.user_contact = 'Contact number must be 10-11 digits';
            isValid = false;
        }

        // Password validation (only if a new password is entered)
        const password = $('#user_pass').val();
        const confirmPassword = $('#user_pass_confirm').val();
        if (password !== '') {
            if (password.length < 10) {
                errors.user_pass = 'Password must be at least 10 characters long';
                isValid = false;
            } else if (!/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{10,}$/.test(password)) {
                errors.user_pass = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character';
                isValid = false;
            } else if (password !== confirmPassword) {
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
});
