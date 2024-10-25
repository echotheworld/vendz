<!-- edit_profile_modal.php -->
<div class="modal fade" id="editProfileModal" tabindex="-1" role="dialog" aria-labelledby="editProfileModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm">
                    <div class="form-group">
                        <label for="user_id">Username</label>
                        <input type="text" class="form-control" id="user_id" name="user_id" minlength="3" maxlength="15" pattern="[a-zA-Z0-9]{3,15}" required>
                        <small class="form-text text-muted">3-15 characters, letters and numbers only</small>
                    </div>
                    <div class="form-group">
                        <label for="user_email">Email</label>
                        <input type="email" class="form-control" id="user_email" name="user_email" maxlength="30" required>
                        <small class="form-text text-muted">Must be a valid email address</small>
                    </div>
                    <div class="form-group">
                        <label for="user_contact">Contact Number</label>
                        <input type="tel" class="form-control" id="user_contact" name="user_contact" pattern="[0-9]{11}" required>
                        <small class="form-text text-muted">Must be 11 digits (e.g., 09xxxxxxxxx)</small>
                    </div>
                    <div class="form-group">
                        <label for="user_pass">New Password (leave blank to keep current)</label>
                        <input type="password" class="form-control" id="user_pass" name="user_pass" minlength="8" maxlength="20">
                        <small class="form-text text-muted">8-20 characters, must include uppercase, lowercase, number, and symbol</small>
                    </div>
                    <div class="form-group">
                        <label for="user_pass_confirm">Confirm New Password</label>
                        <input type="password" class="form-control" id="user_pass_confirm" name="user_pass_confirm" minlength="8" maxlength="20">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="clearFormButton">Clear</button>
                <button type="button" class="btn btn-primary" id="saveProfileChanges">Save changes</button>
            </div>
        </div>
    </div>
</div>
