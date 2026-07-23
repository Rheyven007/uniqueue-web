// assets/js/feedback.js — Feedback form validation & submission

(function () {
    'use strict';

    const feedbackForm = document.getElementById('feedback-form');
    const ratingInput = document.getElementById('rating');
    const ratingStars = document.getElementById('rating-stars');
    const ratingError = document.getElementById('rating-error');
    let selectedRating = 0;

    if (!feedbackForm || !ratingInput || !ratingStars) return;

    // Star rating interaction
    ratingStars.addEventListener('mouseover', function (e) {
        if (e.target.classList.contains('star')) {
            const hoverValue = parseInt(e.target.dataset.value);
            Array.from(ratingStars.children).forEach(star => {
                star.classList.toggle('hover', parseInt(star.dataset.value) <= hoverValue);
            });
        }
    });

    ratingStars.addEventListener('mouseout', function () {
        Array.from(ratingStars.children).forEach(star => star.classList.remove('hover'));
    });

    ratingStars.addEventListener('click', function (e) {
        if (e.target.classList.contains('star')) {
            selectedRating = parseInt(e.target.dataset.value);
            ratingInput.value = selectedRating;
            ratingError.textContent = ''; // Clear error on selection
            Array.from(ratingStars.children).forEach(star => {
                star.classList.toggle('selected', parseInt(star.dataset.value) <= selectedRating);
            });
        }
    });

    // Form submission
    feedbackForm.addEventListener('submit', function (e) {
        e.preventDefault();

        if (selectedRating === 0) {
            ratingError.textContent = 'Please select a rating.';
            return;
        }

        const formData = new FormData(feedbackForm);

        fetch('/api/submit-feedback.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Thank you for your feedback!');
                window.location.href = '/student/student-dashboard.php';
            } else {
                alert(data.message || 'Failed to submit feedback. Please try again.');
            }
        })
        .catch(error => console.error('Error submitting feedback:', error));
    });
})();