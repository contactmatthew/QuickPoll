let optionCount = 2;
let optionImages = {};
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', loadActivePolls);
} else {
    loadActivePolls();
}
function updateExpirationLimits() {
    const expirationType = document.getElementById('expirationType').value;
    const expirationValue = document.getElementById('expirationValue');
    const expirationHint = document.getElementById('expirationHint');
    if (expirationType === 'hours') {
        expirationValue.setAttribute('max', '24');
        expirationValue.setAttribute('min', '1');
        expirationValue.setAttribute('placeholder', 'Max: 24 hours');
        expirationHint.textContent = 'Maximum: 24 hours';
        if (parseInt(expirationValue.value) > 24) {
            expirationValue.value = '24';
        }
    } else {
        expirationValue.setAttribute('max', '30');
        expirationValue.setAttribute('min', '1');
        expirationValue.setAttribute('placeholder', 'Max: 30 days');
        expirationHint.textContent = 'Maximum: 30 days';
        if (parseInt(expirationValue.value) > 30) {
            expirationValue.value = '30';
        }
    }
}
document.getElementById('expirationType').addEventListener('change', updateExpirationLimits);
document.getElementById('expirationValue').addEventListener('input', function() {
    const expirationTypeSelect = document.getElementById('expirationType');
    const expirationType = expirationTypeSelect.value;
    const maxValue = expirationType === 'hours' ? 24 : 30;
    const maxText = expirationType === 'hours' ? '24 hours' : '30 days';
    const currentValue = parseInt(this.value);
    const feedbackElement = document.getElementById('expirationFeedback');
    if (expirationType === 'hours' && currentValue === 24) {
        expirationTypeSelect.value = 'days';
        this.value = 1;
        updateExpirationLimits();
        this.classList.remove('is-invalid');
        feedbackElement.textContent = '';
        return;
    }
    if (currentValue > maxValue) {
        this.value = maxValue;
        this.classList.add('is-invalid');
        feedbackElement.textContent = `Maximum ${maxText} allowed`;
        setTimeout(() => {
            this.classList.remove('is-invalid');
            feedbackElement.textContent = '';
        }, 3000);
    } else if (currentValue < 1) {
        this.value = 1;
        this.classList.add('is-invalid');
        feedbackElement.textContent = 'Minimum value is 1';
        setTimeout(() => {
            this.classList.remove('is-invalid');
            feedbackElement.textContent = '';
        }, 3000);
    } else {
        this.classList.remove('is-invalid');
        feedbackElement.textContent = '';
    }
});
updateExpirationLimits();
function addOption() {
    if (optionCount >= 20) {
        alert('Maximum 20 options allowed');
        return;
    }
    optionCount++;
    const container = document.getElementById('optionsContainer');
    const optionDiv = document.createElement('div');
    optionDiv.className = 'option-item mb-3';
    optionDiv.innerHTML = `
        <div class="input-group">
            <input type="text" class="form-control option-input" placeholder="Option ${optionCount}" required>
            <button type="button" class="btn btn-outline-secondary" onclick="addImageInput(this)">
                <i class="bi bi-image"></i>
            </button>
            <button type="button" class="btn btn-outline-danger" onclick="removeOption(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        <input type="file" class="form-control d-none image-input" accept="image/*" onchange="handleImageUpload(this)" style="position: absolute; opacity: 0; width: 0; height: 0;">
    `;
    container.appendChild(optionDiv);
}
function removeOption(button) {
    if (optionCount <= 2) {
        alert('At least 2 options are required');
        return;
    }
    const optionItem = button.closest('.option-item');
    const index = Array.from(optionItem.parentNode.children).indexOf(optionItem);
    delete optionImages[index];
    optionItem.remove();
    optionCount--;
    const options = document.querySelectorAll('.option-input');
    options.forEach((opt, idx) => {
        opt.placeholder = `Option ${idx + 1}`;
    });
}
function addImageInput(button) {
    const optionItem = button.closest('.option-item');
    const imageInput = optionItem.querySelector('.image-input');
    if (imageInput) {
        imageInput.click();
    }
}
function handleImageUpload(input) {
    const file = input.files[0];
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('Image size must be less than 5MB');
        input.value = '';
        return;
    }
    if (!file.type.startsWith('image/')) {
        alert('Please select a valid image file');
        input.value = '';
        return;
    }
    const reader = new FileReader();
    reader.onload = function(e) {
        const optionItem = input.closest('.option-item');
        const index = Array.from(optionItem.parentNode.children).indexOf(optionItem);
        optionImages[index] = e.target.result;
        showImagePreview(optionItem, e.target.result);
    };
    reader.readAsDataURL(file);
}
function showImagePreview(optionItem, imageData) {
    const existingPreview = optionItem.querySelector('.image-preview-container');
    if (existingPreview) {
        existingPreview.remove();
    }
    const previewDiv = document.createElement('div');
    previewDiv.className = 'image-preview-container';
    previewDiv.innerHTML = `
        <img src="${imageData}" alt="Preview">
        <span class="remove-image" onclick="removeImage(this)">×</span>
    `;
    const inputGroup = optionItem.querySelector('.input-group');
    if (inputGroup) {
        optionItem.insertBefore(previewDiv, inputGroup);
    } else {
        optionItem.insertBefore(previewDiv, optionItem.firstChild);
    }
    const imageInput = optionItem.querySelector('.image-input');
    if (imageInput) {
        imageInput.classList.add('d-none');
    }
}
function removeImage(button) {
    const optionItem = button.closest('.option-item');
    if (!optionItem) return;
    const index = Array.from(optionItem.parentNode.children).indexOf(optionItem);
    delete optionImages[index];
    const imageInput = optionItem.querySelector('.image-input');
    if (imageInput) {
        imageInput.value = '';
        imageInput.classList.add('d-none');
    }
    const previewContainer = button.closest('.image-preview-container');
    if (previewContainer) {
        previewContainer.remove();
    }
}
document.getElementById('pollForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const title = document.getElementById('pollTitle').value.trim();
    if (!title) {
        alert('Please enter a poll title');
        return;
    }
    const options = [];
    const optionInputs = document.querySelectorAll('.option-input');
    optionInputs.forEach((input, index) => {
        const text = input.value.trim();
        if (text) {
            options.push({
                text: text,
                image: optionImages[index] || null
            });
        }
    });
    if (options.length < 2) {
        alert('At least 2 options are required');
        return;
    }
    const expirationType = document.getElementById('expirationType').value;
    const expirationValue = parseInt(document.getElementById('expirationValue').value);
    const maxValue = expirationType === 'hours' ? 24 : 30;
    if (expirationValue > maxValue) {
        alert(`Maximum ${expirationType === 'hours' ? '24 hours' : '30 days'} allowed`);
        return;
    }
    if (expirationValue < 1) {
        alert('Expiration value must be at least 1');
        return;
    }
    const createBtn = document.getElementById('createBtn');
    createBtn.disabled = true;
    createBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    try {
        const response = await fetch('/QuickPoll/api/create_poll.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                title: title,
                options: options,
                expiration_type: expirationType,
                expiration_value: expirationValue,
                password: document.getElementById('pollPassword').value || null
            })
        });
        const data = await response.json();
        if (data.success) {
            const basePath = window.location.pathname.replace(/\/[^/]*$/, '').replace(/\/index\.html$/, '');
            const pollLink = window.location.origin + basePath + '/poll/' + data.poll_id;
            document.getElementById('pollLink').value = pollLink;
            document.getElementById('viewPollLink').href = pollLink;
            document.getElementById('createPollSection').classList.add('d-none');
            document.getElementById('pollCreatedSection').classList.remove('d-none');
            setTimeout(() => {
                loadActivePolls();
            }, 500);
        } else {
            alert(data.message || 'Failed to create poll');
            createBtn.disabled = false;
            createBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Create Poll';
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        createBtn.disabled = false;
        createBtn.innerHTML = '<i class="bi bi-plus-circle"></i> Create Poll';
    }
});
function copyLink() {
    const linkInput = document.getElementById('pollLink');
    linkInput.select();
    linkInput.setSelectionRange(0, 99999); // For mobile devices
    try {
        document.execCommand('copy');
        const copyBtn = event.target.closest('button');
        const originalHTML = copyBtn.innerHTML;
        copyBtn.innerHTML = '<i class="bi bi-check"></i> Copied!';
        setTimeout(() => {
            copyBtn.innerHTML = originalHTML;
        }, 2000);
    } catch (err) {
        alert('Failed to copy link');
    }
}
function createNewPoll() {
    document.getElementById('pollForm').reset();
    document.getElementById('optionsContainer').innerHTML = `
        <div class="option-item mb-3">
            <div class="input-group">
                <input type="text" class="form-control option-input" placeholder="Option 1" required>
                <button type="button" class="btn btn-outline-secondary" onclick="addImageInput(this)">
                    <i class="bi bi-image"></i>
                </button>
            </div>
            <input type="file" class="form-control d-none image-input" accept="image/*" onchange="handleImageUpload(this)" style="position: absolute; opacity: 0; width: 0; height: 0;">
        </div>
        <div class="option-item mb-3">
            <div class="input-group">
                <input type="text" class="form-control option-input" placeholder="Option 2" required>
                <button type="button" class="btn btn-outline-secondary" onclick="addImageInput(this)">
                    <i class="bi bi-image"></i>
                </button>
            </div>
            <input type="file" class="form-control d-none image-input" accept="image/*" onchange="handleImageUpload(this)" style="position: absolute; opacity: 0; width: 0; height: 0;">
        </div>
    `;
    optionCount = 2;
    optionImages = {};
    document.getElementById('expirationType').value = 'days';
    document.getElementById('expirationValue').value = '7';
    updateExpirationLimits();
    document.getElementById('createPollSection').classList.remove('d-none');
    document.getElementById('pollCreatedSection').classList.add('d-none');
    loadActivePolls();
}
let currentPage = 1;
async function loadActivePolls(page = 1) {
    currentPage = page;
    try {
        const response = await fetch(`/QuickPoll/api/get_all_polls.php?page=${page}&per_page=10`);
        const data = await response.json();
        if (data.success) {
            displayActivePolls(data);
        } else {
            showNoPolls();
        }
    } catch (error) {
        console.error('Error loading polls:', error);
        showNoPolls();
    }
}
function displayActivePolls(data) {
    const loadingSection = document.getElementById('loadingPolls');
    const pollsList = document.getElementById('pollsList');
    const noPolls = document.getElementById('noPolls');
    const pollsCount = document.getElementById('pollsCount');
    const paginationContainer = document.getElementById('paginationContainer');
    loadingSection.classList.add('d-none');
    if (data.polls.length === 0) {
        showNoPolls();
        return;
    }
    pollsCount.textContent = data.total + ' poll' + (data.total > 1 ? 's' : '');
    pollsList.classList.remove('d-none');
    pollsList.innerHTML = '';
    data.polls.forEach(poll => {
        const pollCard = createPollCard(poll);
        pollsList.appendChild(pollCard);
    });
    if (data.total_pages > 1) {
        paginationContainer.classList.remove('d-none');
        displayPagination(data.page, data.total_pages);
    } else {
        paginationContainer.classList.add('d-none');
    }
    startCountdown();
}
function createPollCard(poll) {
    const card = document.createElement('div');
    card.className = 'active-poll-card mb-3';
    card.dataset.expiresAt = poll.expires_at; // Store expiration time for countdown
    const createdDate = new Date(poll.created_at);
    const formattedDate = createdDate.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'short', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    const expiresAt = new Date(poll.expires_at);
    const countdownText = getCountdownText(expiresAt);
    card.innerHTML = `
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <h5 class="mb-2">
                    <a href="${escapeHtml(poll.poll_url_view || poll.poll_url)}" class="poll-link">
                        ${escapeHtml(poll.title)}
                    </a>
                </h5>
                <div class="poll-meta text-muted small mb-2">
                    <i class="bi bi-calendar"></i> Created: ${formattedDate}
                    <span class="ms-3">
                        <i class="bi bi-clock"></i> Expires in: <span class="countdown-timer" data-expires="${poll.expires_at}">${countdownText}</span>
                    </span>
                </div>
                <div class="poll-stats">
                    <span class="badge bg-secondary me-2">
                        <i class="bi bi-list-ul"></i> ${poll.option_count} options
                    </span>
                    <span class="badge bg-info">
                        <i class="bi bi-people"></i> ${poll.total_votes} vote${poll.total_votes !== 1 ? 's' : ''}
                    </span>
                </div>
            </div>
            <div class="ms-3">
                <a href="${escapeHtml(poll.poll_url_view || poll.poll_url)}" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-eye"></i> View
                </a>
            </div>
        </div>
    `;
    return card;
}
function getCountdownText(expiresAt) {
    const now = new Date();
    const expires = new Date(expiresAt);
    const diff = expires - now;
    if (diff <= 0) {
        return 'Expired';
    }
    const days = Math.floor(diff / (1000 * 60 * 60 * 24));
    const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
    if (days > 0) {
        return `${days}d ${hours}h ${minutes}m`;
    } else if (hours > 0) {
        return `${hours}h ${minutes}m ${seconds}s`;
    } else if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
    } else {
        return `${seconds}s`;
    }
}
function updateCountdowns() {
    const timers = document.querySelectorAll('.countdown-timer');
    timers.forEach(timer => {
        const expiresAt = new Date(timer.dataset.expires);
        const now = new Date();
        if (now >= expiresAt) {
            timer.classList.add('text-danger');
            timer.textContent = 'Expired';
            const pollCard = timer.closest('.active-poll-card');
            if (pollCard) {
                setTimeout(() => {
                    pollCard.style.transition = 'opacity 0.5s';
                    pollCard.style.opacity = '0';
                    setTimeout(() => {
                        pollCard.remove();
                        const pollsList = document.getElementById('pollsList');
                        if (pollsList && pollsList.children.length === 0) {
                            loadActivePolls(1);
                        }
                    }, 500);
                }, 2000); // Wait 2 seconds before removing
            }
        } else {
            const countdownText = getCountdownText(expiresAt);
            timer.textContent = countdownText;
            timer.classList.remove('text-danger');
        }
    });
}
let countdownInterval = null;
function startCountdown() {
    if (countdownInterval) {
        clearInterval(countdownInterval);
    }
    updateCountdowns(); // Update immediately
    countdownInterval = setInterval(updateCountdowns, 1000); // Update every second
}
function displayPagination(currentPage, totalPages) {
    const paginationContainer = document.getElementById('paginationContainer');
    let paginationHTML = '<nav aria-label="Polls pagination"><ul class="pagination justify-content-center mb-0">';
    if (currentPage > 1) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivePolls(${currentPage - 1}); return false;">Previous</a></li>`;
    } else {
        paginationHTML += `<li class="page-item disabled"><span class="page-link">Previous</span></li>`;
    }
    const maxVisible = 5;
    let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }
    if (startPage > 1) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivePolls(1); return false;">1</a></li>`;
        if (startPage > 2) {
            paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
    }
    for (let i = startPage; i <= endPage; i++) {
        if (i === currentPage) {
            paginationHTML += `<li class="page-item active"><span class="page-link">${i}</span></li>`;
        } else {
            paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivePolls(${i}); return false;">${i}</a></li>`;
        }
    }
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationHTML += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivePolls(${totalPages}); return false;">${totalPages}</a></li>`;
    }
    if (currentPage < totalPages) {
        paginationHTML += `<li class="page-item"><a class="page-link" href="#" onclick="loadActivePolls(${currentPage + 1}); return false;">Next</a></li>`;
    } else {
        paginationHTML += `<li class="page-item disabled"><span class="page-link">Next</span></li>`;
    }
    paginationHTML += '</ul></nav>';
    paginationContainer.innerHTML = paginationHTML;
}
function showNoPolls() {
    document.getElementById('loadingPolls').classList.add('d-none');
    document.getElementById('pollsList').classList.add('d-none');
    document.getElementById('paginationContainer').classList.add('d-none');
    document.getElementById('noPolls').classList.remove('d-none');
    document.getElementById('pollsCount').textContent = '0 polls';
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
document.addEventListener('DOMContentLoaded', function() {
    const donationModal = document.getElementById('donationModal');
    if (donationModal) {
        const modalInstance = new bootstrap.Modal(donationModal, {
            backdrop: true,
            keyboard: true,
            focus: true
        });
        donationModal.addEventListener('show.bs.modal', function() {
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '0';
            const thankYouMessage = this.querySelector('.thank-you-message');
            const qrContainer = this.querySelector('.qr-code-container');
            if (thankYouMessage) {
                thankYouMessage.style.animation = 'none';
                setTimeout(() => {
                    thankYouMessage.style.animation = '';
                }, 10);
            }
            if (qrContainer) {
                qrContainer.style.animation = 'none';
                setTimeout(() => {
                    qrContainer.style.animation = '';
                }, 10);
            }
        });
        donationModal.addEventListener('shown.bs.modal', function() {
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '0';
        });
        donationModal.addEventListener('hide.bs.modal', function() {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        });
    }
});
