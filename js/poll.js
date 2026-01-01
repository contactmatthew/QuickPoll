function getBasePath() {
    const path = window.location.pathname;
    if (path.includes('/QuickPoll/')) {
        return '/QuickPoll/';
    }
    return '/QuickPoll/';
}
const BASE_PATH = getBasePath();
let pollId = null;
let isViewOnly = false; // Flag to disable voting if accessed from homepage
let viewToken = null; // Store the view token if present
let pollData = null;
let hasVoted = false;
let requiresPassword = false;
let storedPassword = null; // Store password after successful validation
const pathMatch = window.location.pathname.match(/\/poll\/([a-zA-Z0-9]+)$/);
if (pathMatch) {
    pollId = pathMatch[1];
} else {
    const urlParams = new URLSearchParams(window.location.search);
    pollId = urlParams.get('id');
}
const urlParams = new URLSearchParams(window.location.search);
viewToken = urlParams.get('view_token');
if (!pollId) {
    showError('Poll ID is missing');
} else {
    loadPoll();
}
async function loadPoll() {
    try {
        let url = `${BASE_PATH}api/get_poll.php?id=${pollId}`;
        if (viewToken) {
            url += `&view_token=${encodeURIComponent(viewToken)}`;
        }
        if (storedPassword) {
            url += `&password=${encodeURIComponent(storedPassword)}`;
        }
        const response = await fetch(url);
        const data = await response.json();
        if (data.success) {
            pollData = data;
            hasVoted = data.has_voted;
            isViewOnly = data.is_view_only === true;
            requiresPassword = data.requires_password === true;
            if (requiresPassword && !storedPassword) {
                showPasswordPrompt();
            } else {
                displayPoll(data);
            }
        } else {
            if (data.requires_password) {
                storedPassword = null; // Clear invalid password
                showPasswordPrompt();
                const errorDiv = document.getElementById('passwordError');
                errorDiv.textContent = data.message || 'Incorrect password';
                errorDiv.classList.remove('d-none');
            } else {
                showError(data.message || 'Poll not found');
            }
        }
    } catch (error) {
        console.error('Error:', error);
        showError('Failed to load poll. Please try again.');
    }
}
function displayPoll(data) {
    document.getElementById('loadingSection').classList.add('d-none');
    document.getElementById('passwordSection').classList.add('d-none');
    document.getElementById('pollSection').classList.remove('d-none');
    const poll = data.poll;
    const options = data.options;
    const isExpired = data.is_expired;
    hasVoted = data.has_voted;
    const userVoteOptionId = data.user_vote_option_id || null;
    document.getElementById('pollTitle').textContent = poll.title;
    if (isExpired) {
        document.getElementById('pollInfo').classList.add('d-none');
        document.getElementById('expiredMessage').classList.add('d-none');
        document.getElementById('voteMessage').classList.add('d-none');
    } else {
        const expiresAt = new Date(poll.expires_at);
        const expiresText = expiresAt.toLocaleString();
        document.getElementById('pollInfo').textContent = `Expires: ${expiresText}`;
        document.getElementById('pollInfo').classList.remove('d-none');
        if (hasVoted) {
            document.getElementById('voteMessage').classList.remove('d-none');
        } else if (isViewOnly && !requiresPassword) {
            const voteMessage = document.getElementById('voteMessage');
            voteMessage.classList.remove('d-none');
            voteMessage.classList.remove('alert-info');
            voteMessage.classList.add('alert-warning');
            voteMessage.innerHTML = '<i class="bi bi-info-circle"></i> This is a view-only mode. Use the direct poll link to vote.';
        } else if (isViewOnly && requiresPassword) {
            const voteMessage = document.getElementById('voteMessage');
            voteMessage.classList.remove('d-none');
            voteMessage.classList.remove('alert-info');
            voteMessage.classList.add('alert-warning');
            voteMessage.innerHTML = '<i class="bi bi-lock"></i> This poll is password protected. Enter the password when voting.';
        } else {
            document.getElementById('voteMessage').classList.add('d-none');
        }
    }
    const optionsContainer = document.getElementById('optionsContainer');
    optionsContainer.innerHTML = '';
    if (isExpired) {
        if (data.winner) {
            const winnerCard = createOptionCard(data.winner, data, isExpired, userVoteOptionId);
            optionsContainer.appendChild(winnerCard);
            const allOptionsForRankings = data.allOptions || options;
            if (allOptionsForRankings.length > 1) {
                const buttonContainer = document.createElement('div');
                buttonContainer.className = 'text-center mt-4 mb-3';
                buttonContainer.innerHTML = `
                    <button type="button" class="btn btn-outline-secondary" id="showOtherRankingsBtn" onclick="toggleOtherRankings()">
                        <i class="bi bi-trophy"></i> Show Other Rankings
                    </button>
                `;
                optionsContainer.appendChild(buttonContainer);
                const otherRankingsContainer = document.createElement('div');
                otherRankingsContainer.id = 'otherRankingsContainer';
                otherRankingsContainer.className = 'd-none';
                otherRankingsContainer.innerHTML = '<h5 class="text-center mb-3 mt-4"><i class="bi bi-list-ol"></i> Other Rankings</h5>';
                optionsContainer.appendChild(otherRankingsContainer);
                pollData.allOptions = allOptionsForRankings;
            }
        } else if (options.length > 0) {
            const winnerCard = createOptionCard(options[0], data, isExpired, userVoteOptionId);
            optionsContainer.appendChild(winnerCard);
        } else {
            optionsContainer.innerHTML = '<div class="alert alert-info text-center">This poll expired with no votes.</div>';
        }
    } else {
        options.forEach(option => {
            const optionCard = createOptionCard(option, data, isExpired, userVoteOptionId);
            optionsContainer.appendChild(optionCard);
        });
    }
}
function createOptionCard(option, pollData, isExpired, userVoteOptionId) {
    const card = document.createElement('div');
    if (isExpired) {
        card.className = 'option-card fade-in winner text-center';
        card.style.pointerEvents = 'none'; // Disable all interactions
        card.style.cursor = 'default';
        let cardHTML = '<div class="winner-display">';
        if (option.image_path) {
            cardHTML += `
                <div class="winner-image-wrapper mb-3">
                    <img src="${escapeHtml(option.image_path)}" alt="${escapeHtml(option.option_text)}" class="winner-image" onerror="this.style.display='none'">
                </div>
            `;
        }
        cardHTML += `
            <div class="winner-name">
                <i class="bi bi-trophy-fill text-warning display-4"></i>
                <h3 class="mt-3 mb-2">${escapeHtml(option.option_text)}</h3>
                <p class="text-success mb-0"><strong>WINNER</strong></p>
            </div>
        `;
        cardHTML += '</div>';
        card.innerHTML = cardHTML;
        return card;
    }
    card.className = 'option-card fade-in';
    if (userVoteOptionId && option.id === userVoteOptionId) {
        card.classList.add('voted');
    }
    let cardHTML = '';
    if (option.image_path) {
        cardHTML += `
            <div class="option-image-wrapper">
                <img src="${escapeHtml(option.image_path)}" alt="${escapeHtml(option.option_text)}" class="option-image" onerror="this.style.display='none'">
                ${!hasVoted && (!isViewOnly || (isViewOnly && requiresPassword)) ? '<div class="image-click-hint">Click to vote</div>' : ''}
            </div>
        `;
    }
    cardHTML += '<div class="option-card-content">';
    cardHTML += `
        <div class="option-text">${escapeHtml(option.option_text)}</div>
        <div class="option-votes">${option.vote_count} vote${option.vote_count !== 1 ? 's' : ''} (${option.percentage}%)</div>
    `;
    if (hasVoted) {
        cardHTML += `
            <div class="progress">
                <div class="progress-bar" role="progressbar" style="width: ${option.percentage}%" 
                     aria-valuenow="${option.percentage}" aria-valuemin="0" aria-valuemax="100"></div>
            </div>
        `;
    }
    cardHTML += '</div>';
    card.innerHTML = cardHTML;
    if (!hasVoted && (!isViewOnly || (isViewOnly && requiresPassword))) {
        card.style.cursor = 'pointer';
        card.addEventListener('click', () => vote(option.id));
        const imageWrapper = card.querySelector('.option-image-wrapper');
        if (imageWrapper) {
            imageWrapper.style.cursor = 'pointer';
        }
    } else if (isViewOnly) {
        card.style.cursor = 'not-allowed';
        card.style.opacity = '0.8';
        const imageWrapper = card.querySelector('.option-image-wrapper');
        if (imageWrapper) {
            imageWrapper.style.cursor = 'not-allowed';
        }
    }
    return card;
}
async function vote(optionId) {
    if (hasVoted) { return; }
    let password = storedPassword;
    if (isViewOnly && requiresPassword && !password) {
        password = await promptPassword();
        if (!password) { return; } // User cancelled
        storedPassword = password; // Store for subsequent votes
    }
    if (isViewOnly && !requiresPassword) {
        const viewOnlyModal = new bootstrap.Modal(document.getElementById('viewOnlyModal'));
        viewOnlyModal.show();
        return;
    }
    try {
        const voteData = {
            poll_id: pollId,
            option_id: optionId,
            password: password // Include password if available
        };
        if (viewToken) {
            voteData.view_token = viewToken; // Include view token
        }
        const response = await fetch(`${BASE_PATH}api/vote.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(voteData)
        });
        const data = await response.json();
        if (data.success) {
            hasVoted = true;
            pollData.has_voted = true;
            pollData.user_vote_option_id = data.user_vote_option_id;
            pollData.options.forEach(option => {
                if (option.id === optionId) {
                    option.vote_count++;
                }
            });
            const totalVotes = pollData.options.reduce((sum, opt) => sum + opt.vote_count, 0);
            pollData.options.forEach(option => {
                option.percentage = totalVotes > 0 ? Math.round((option.vote_count / totalVotes) * 100 * 10) / 10 : 0;
            });
            pollData.total_votes = totalVotes;
            displayPoll(pollData);
        } else if (data.requires_password) {
            alert(data.message);
            storedPassword = null; // Clear stored password on failure
        } else {
            alert(data.message || 'Failed to vote');
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}
function promptPassword() {
    return new Promise((resolve) => {
        const password = prompt('This poll is password protected. Please enter the password to vote:');
        resolve(password);
    });
}
function showPasswordPrompt() {
    document.getElementById('loadingSection').classList.add('d-none');
    document.getElementById('passwordSection').classList.remove('d-none');
    const passwordInput = document.getElementById('pollPasswordInput');
    passwordInput.focus();
    passwordInput.value = ''; // Clear previous input
    const newInput = passwordInput.cloneNode(true);
    passwordInput.parentNode.replaceChild(newInput, passwordInput);
    document.getElementById('pollPasswordInput').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            submitPassword();
        }
    });
}
async function submitPassword() {
    const passwordInput = document.getElementById('pollPasswordInput');
    const password = passwordInput.value.trim();
    const errorDiv = document.getElementById('passwordError');
    if (!password) {
        errorDiv.textContent = 'Please enter a password';
        errorDiv.classList.remove('d-none');
        return;
    }
    storedPassword = password;
    document.getElementById('passwordSection').classList.add('d-none');
    document.getElementById('loadingSection').classList.remove('d-none');
    errorDiv.classList.add('d-none');
    loadPoll();
}
function showError(message) {
    document.getElementById('loadingSection').classList.add('d-none');
    document.getElementById('passwordSection').classList.add('d-none');
    document.getElementById('errorSection').classList.remove('d-none');
    document.getElementById('errorMessage').textContent = message;
}
function toggleOtherRankings() {
    const container = document.getElementById('otherRankingsContainer');
    const button = document.getElementById('showOtherRankingsBtn');
    if (!container || !button) return;
    if (container.classList.contains('d-none')) {
        container.classList.remove('d-none');
        button.innerHTML = '<i class="bi bi-chevron-up"></i> Hide Other Rankings';
        const allOptions = pollData.allOptions || [];
        const winnerId = pollData.winner ? pollData.winner.id : null;
        const header = container.querySelector('h5');
        container.innerHTML = '';
        if (header) container.appendChild(header);
        const otherOptions = allOptions.filter(option => option.id !== winnerId);
        otherOptions.forEach((option, index) => {
            const rank = index + 2;
            const rankingCard = createRankingCard(option, rank, pollData);
            container.appendChild(rankingCard);
        });
    } else {
        container.classList.add('d-none');
        button.innerHTML = '<i class="bi bi-trophy"></i> Show Other Rankings';
    }
}
function createRankingCard(option, rank, pollData) {
    const card = document.createElement('div');
    card.className = 'ranking-card mb-2';
    let cardHTML = '<div class="d-flex align-items-center gap-3">';
    cardHTML += `<div class="ranking-number text-muted">#${rank}</div>`;
    if (option.image_path) {
        cardHTML += `
            <div class="ranking-image-wrapper">
                <img src="${escapeHtml(option.image_path)}" alt="${escapeHtml(option.option_text)}" class="ranking-image" onerror="this.style.display='none'">
            </div>
        `;
    }
    cardHTML += `
        <div class="flex-grow-1">
            <div class="ranking-text">${escapeHtml(option.option_text)}</div>
            <div class="ranking-votes text-muted small">${option.vote_count} vote${option.vote_count !== 1 ? 's' : ''} (${option.percentage || 0}%)</div>
        </div>
    `;
    cardHTML += '</div>';
    card.innerHTML = cardHTML;
    return card;
}
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
document.addEventListener('DOMContentLoaded', function() {
    const donationModal = document.getElementById('donationModal');
    if (donationModal) {
        donationModal.addEventListener('show.bs.modal', function() {
            document.body.style.overflow = 'auto';
            document.body.style.paddingRight = '0';
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
