<!-- Password Prompt Modal -->
<div class="modal fade" id="passwordPromptModal" tabindex="-1" role="dialog" aria-labelledby="passwordPromptModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordPromptModalLabel">Authentication Required</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="mb-3">You are about to access User Management. For security purposes, please enter your administrator password to proceed.</p>
                <input type="password" id="adminPasswordPrompt" class="form-control" placeholder="Enter your administrator password">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmPasswordPrompt">Verify</button>
            </div>
        </div>
    </div>
</div>

<style>
    #passwordPromptModal .modal-content {
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }
    #passwordPromptModal .modal-header {
        background-color: #369c43;
        color: white;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    #passwordPromptModal .close {
        color: white;
    }
    #passwordPromptModal .modal-body {
        padding: 20px;
    }
    #passwordPromptModal .modal-footer {
        border-top: none;
        padding: 15px 20px;
    }
    #adminPasswordPrompt {
        border: 1px solid #ced4da;
        border-radius: 4px;
        padding: 10px;
    }
    #confirmPasswordPrompt {
        background-color: #369c43;
        border-color: #369c43;
    }
    #confirmPasswordPrompt:hover {
        background-color: #2a7d35;
        border-color: #2a7d35;
    }
</style>