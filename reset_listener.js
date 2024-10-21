// Function to get the existing Firebase app
function getExistingFirebaseApp() {
    if (firebase.apps.length > 0) {
        return firebase.apps[0];
    } else {
        console.error('Firebase app not initialized');
        return null;
    }
}

// Get the existing Firebase app
const app = getExistingFirebaseApp();

if (app) {
    // Listen for changes in the first_login status
    app.database().ref('tables/user/-O8FwN7EsoD-lRKW8z8z/first_login').on('value', (snapshot) => {
        if (snapshot.val() === true) {
            // Show reset message
            alert("System has been reset. You will be logged out for security reasons. Please log in again with the default credentials.");
            
            // Set a short delay before logout to ensure the alert is seen
            setTimeout(logout, 100);
        }
    });
} else {
    console.error('Could not set up reset listener due to missing Firebase app');
}

function logout() {
    // Perform any necessary cleanup
    // Then redirect to the logout page
    window.location.href = 'logout.php';
}
