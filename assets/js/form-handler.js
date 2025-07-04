/**
 * Mass Mailer Frontend Form Handler
 *
 * This JavaScript handles the AJAX submission of the subscription form
 * and displays messages to the user.
 *
 * @package Mass_Mailer
 * @subpackage Assets
 */

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('mass-mailer-subscription-form');
    const messagesDiv = document.getElementById('mass-mailer-form-messages');

    if (form && messagesDiv) {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent default form submission

            messagesDiv.innerHTML = ''; // Clear previous messages
            messagesDiv.className = 'mass-mailer-form-messages'; // Reset classes

            const submitButton = form.querySelector('button[type="submit"]');
            submitButton.disabled = true; // Disable button to prevent multiple submissions
            submitButton.textContent = 'Subscribing...'; // Change button text

            const formData = new FormData(form);
            // Add an action parameter for the PHP AJAX handler
            // In a WordPress context, this would be 'action=mass_mailer_subscribe'
            // For a standalone app, this might be a specific API endpoint URL
            // For this example, we'll assume the mass-mailer.php handles it via a query param or similar.
            // Adjust the URL below to your actual AJAX endpoint.
            const ajaxUrl = 'mass-mailer.php?action=mass_mailer_subscribe'; // Example AJAX URL

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    // Handle HTTP errors (e.g., 404, 500)
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json(); // Parse JSON response
            })
            .then(data => {
                if (data.success) {
                    messagesDiv.innerHTML = `<div class="success">${data.message}</div>`;
                    form.reset(); // Clear form fields on success
                } else {
                    messagesDiv.innerHTML = `<div class="error">${data.message}</div>`;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messagesDiv.innerHTML = `<div class="error">There was a problem with your subscription. Please try again.</div>`;
            })
            .finally(() => {
                submitButton.disabled = false; // Re-enable button
                submitButton.textContent = 'Subscribe'; // Restore button text
            });
        });
    }
});
