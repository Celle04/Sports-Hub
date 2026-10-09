<section class="student-sports">
    <header class="student-sports-heading">
        <div>
            <span class="student-sports-kicker">SNNHS ATHLETICS</span>
            <h2>Explore sports programs</h2>
            <p>Meet the programs and coaches building our teams.</p>
        </div>
        <span class="student-sports-count">{{ ($studentSports ?? collect())->count() }} {{ ($studentSports ?? collect())->count() === 1 ? 'program' : 'programs' }}</span>
    </header>

    <div class="student-sports-grid">
        @forelse ($studentSports ?? [] as $sport)
            <article
                class="card student-sport-card"
                tabindex="0"
                role="button"
                aria-haspopup="dialog"
                aria-controls="student-sport-modal"
                data-members-url="{{ route('student.sports.members', $sport) }}"
                aria-label="View enrolled members in {{ $sport->name }}"
            >
                <div class="student-sport-card-top">
                    <span class="student-sport-icon" aria-hidden="true"><svg><use href="#icon-trophy"></use></svg></span>
                    <span class="student-sport-classification">{{ $sport->classification ?? 'Sports program' }}</span>
                </div>
                <h3>{{ $sport->name }}</h3>
                <p class="student-sport-description">{{ $sport->description ?: 'Program details will be available soon.' }}</p>
                <div class="student-sport-coach">
                    <svg aria-hidden="true"><use href="#icon-user"></use></svg>
                    <span><small>PROGRAM COACH</small><strong>{{ $sport->coaches->first()?->name ?? 'Not assigned' }}</strong></span>
                </div>
                <span class="student-sport-view-hint">View Members <span aria-hidden="true">&rarr;</span></span>
            </article>
        @empty
            <p class="student-sports-empty">No active sports programs are available.</p>
        @endforelse
    </div>

    <div class="student-sport-modal" id="student-sport-modal" hidden>
        <section class="student-sport-dialog" role="dialog" aria-modal="true" aria-labelledby="student-sport-modal-title" tabindex="-1">
            <div class="student-sport-program-view">
                <header class="student-sport-modal-header">
                    <div>
                        <span class="student-sport-modal-kicker">SNNHS ATHLETICS</span>
                        <h2 id="student-sport-modal-title">Sports program</h2>
                        <p class="student-sport-modal-description"></p>
                        <span class="student-sport-modal-classification"></span>
                    </div>
                    <button class="student-sport-modal-close" type="button" aria-label="Close sports program details">&times;</button>
                </header>

                <div class="student-sport-summary" aria-live="polite">
                    <div><small>ENROLLED MEMBERS</small><strong class="student-sport-total">0</strong></div>
                    <div><small>PROGRAM COACH</small><strong class="student-sport-coach-name">Not assigned</strong></div>
                    <div><small>PENDING APPLICATIONS</small><strong class="student-sport-pending">0</strong></div>
                </div>

                <section class="student-sport-roster" aria-labelledby="student-sport-roster-title">
                    <div class="student-sport-roster-heading">
                        <div><h3 id="student-sport-roster-title">Enrolled students</h3><p>Approved and active students in this program.</p></div>
                    </div>
                    <div class="student-sport-filters">
                        <label>
                            <span class="sr-only">Search by student name or ID</span>
                            <input class="student-sport-search" type="search" placeholder="Search name or student ID">
                        </label>
                        <label>
                            <span class="sr-only">Filter by grade</span>
                            <select class="student-sport-grade"><option value="">All grades</option></select>
                        </label>
                        <label>
                            <span class="sr-only">Filter by enrollment status</span>
                            <select class="student-sport-status">
                                <option value="">All statuses</option>
                                <option value="Approved">Approved</option>
                                <option value="Active">Active</option>
                            </select>
                        </label>
                    </div>
                    <p class="student-sport-message" role="status" aria-live="polite">Loading enrolled students...</p>
                    <div class="student-sport-table-wrap" hidden>
                        <table class="student-sport-table">
                            <thead><tr><th scope="col">Student</th><th scope="col">Student ID</th><th scope="col">Grade</th><th scope="col">Status</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <nav class="student-sport-pagination" aria-label="Enrolled student pages" hidden>
                        <button type="button" class="student-sport-previous">Previous</button>
                        <span class="student-sport-page-label"></span>
                        <button type="button" class="student-sport-next">Next</button>
                    </nav>
                </section>
            </div>

            <section class="student-athlete-profile-view" aria-labelledby="student-athlete-profile-name" hidden>
                <header class="student-athlete-profile-actions">
                    <button class="student-athlete-back" type="button">&larr; Back to Members</button>
                    <button class="student-athlete-close" type="button" aria-label="Close athlete profile">&times;</button>
                </header>
                <p class="student-athlete-profile-message" role="status" aria-live="polite">Loading athlete profile...</p>
                <div class="student-athlete-profile-content" hidden>
                    <div class="student-athlete-profile-identity">
                        <img class="student-athlete-profile-photo" alt="" hidden>
                        <span class="student-athlete-profile-initial" aria-hidden="true"></span>
                        <div>
                            <span class="student-sport-modal-kicker">ATHLETE PROFILE</span>
                            <h2 id="student-athlete-profile-name"></h2>
                            <p class="student-athlete-profile-id"></p>
                            <span class="student-athlete-profile-status"></span>
                        </div>
                    </div>
                    <div class="student-athlete-profile-fields">
                        <section>
                            <h3>Student information</h3>
                            <dl>
                                <div><dt>Grade</dt><dd data-profile-field="grade"></dd></div>
                                <div><dt>Status</dt><dd data-profile-field="status"></dd></div>
                            </dl>
                        </section>
                        <section>
                            <h3>Sports information</h3>
                            <dl>
                                <div><dt>Sport</dt><dd data-profile-field="sport"></dd></div>
                                <div><dt>Position / role</dt><dd data-profile-field="position"></dd></div>
                                <div><dt>Team</dt><dd data-profile-field="team"></dd></div>
                                <div><dt>Coach</dt><dd data-profile-field="coach"></dd></div>
                                <div><dt>Jersey number</dt><dd data-profile-field="jersey"></dd></div>
                                <div><dt>Enrollment approved</dt><dd data-profile-field="date_joined"></dd></div>
                            </dl>
                        </section>
                    </div>
                </div>
            </section>
        </section>
    </div>
</section>

<style>
    .student-sport-card {
        cursor: pointer;
        transition: transform 180ms ease, box-shadow 180ms ease, border-color 180ms ease;
    }

    .student-sport-card:hover,
    .student-sport-card:focus-visible {
        transform: translateY(-2px);
        border-color: #8b1e2d;
        box-shadow: 0 10px 22px rgba(50, 15, 21, 0.12);
        outline: none;
    }

    .student-sport-view-hint {
        display: inline-flex;
        gap: 0.35rem;
        margin-top: 1rem;
        color: #7f1d2d;
        font-size: 0.82rem;
        font-weight: 700;
    }

    .student-sport-modal[hidden],
    .student-sport-table-wrap[hidden],
    .student-sport-pagination[hidden] {
        display: none;
    }

    .student-sport-modal {
        position: fixed;
        z-index: 1000;
        inset: 0;
        display: grid;
        place-items: center;
        overflow-y: auto;
        padding: 1rem;
        background: rgba(19, 17, 18, 0.62);
        animation: student-sport-fade-in 160ms ease-out;
    }

    .student-sport-dialog {
        width: min(760px, 100%);
        max-height: min(90vh, 900px);
        overflow-y: auto;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 24px 70px rgba(0, 0, 0, 0.24);
        animation: student-sport-slide-in 180ms ease-out;
    }

    .student-sport-modal-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1.5rem;
        border-bottom: 1px solid #eee7e8;
    }

    .student-sport-modal-kicker {
        color: #8b1e2d;
        font-size: 0.72rem;
        font-weight: 800;
        letter-spacing: 0.1em;
    }

    .student-sport-modal-header h2 {
        margin: 0.3rem 0;
        color: #251c1e;
        font-size: clamp(1.35rem, 4vw, 1.8rem);
    }

    .student-sport-modal-description,
    .student-sport-roster-heading p {
        margin: 0.3rem 0 0;
        color: #6b6264;
    }

    .student-sport-modal-classification {
        display: inline-block;
        margin-top: 0.8rem;
        border-radius: 99px;
        padding: 0.3rem 0.65rem;
        background: #f8ecee;
        color: #7f1d2d;
        font-size: 0.78rem;
        font-weight: 700;
    }

    .student-sport-modal-close {
        flex: 0 0 auto;
        width: 2.4rem;
        height: 2.4rem;
        border: 0;
        border-radius: 50%;
        background: #f5f1f2;
        color: #3a3032;
        font-size: 1.6rem;
        line-height: 1;
        cursor: pointer;
    }

    .student-sport-summary {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.75rem;
        padding: 1rem 1.5rem;
        background: #fcf9fa;
    }

    .student-sport-summary > div {
        display: grid;
        gap: 0.3rem;
    }

    .student-sport-summary small {
        color: #766c6e;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.04em;
    }

    .student-sport-summary strong {
        overflow-wrap: anywhere;
        color: #302528;
    }

    .student-sport-roster {
        padding: 1.25rem 1.5rem 1.5rem;
    }

    .student-sport-roster-heading h3 {
        margin: 0;
        color: #302528;
    }

    .student-sport-filters {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) minmax(120px, 0.55fr) minmax(130px, 0.6fr);
        gap: 0.6rem;
        margin: 1rem 0;
    }

    .student-sport-filters input,
    .student-sport-filters select {
        width: 100%;
        min-height: 2.65rem;
        border: 1px solid #ddd4d6;
        border-radius: 0.55rem;
        padding: 0.55rem 0.7rem;
        background: #fff;
        color: #302528;
        font: inherit;
    }

    .student-sport-message {
        margin: 1rem 0;
        color: #64595b;
    }

    .student-sport-message.is-error {
        color: #a11d2c;
    }

    .student-sport-table-wrap {
        overflow-x: auto;
        border: 1px solid #eee7e8;
        border-radius: 0.65rem;
    }

    .student-sport-table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }

    .student-sport-table th,
    .student-sport-table td {
        padding: 0.7rem 0.75rem;
        border-bottom: 1px solid #f0ebec;
        white-space: nowrap;
    }

    .student-sport-table th {
        background: #fcf9fa;
        color: #6b6264;
        font-size: 0.74rem;
        text-transform: uppercase;
    }

    .student-sport-table tr:last-child td {
        border-bottom: 0;
    }

    .student-sport-student {
        display: inline-flex;
        align-items: center;
        gap: 0.6rem;
    }

    .student-sport-avatar,
    .student-sport-avatar-fallback {
        display: inline-grid;
        width: 2.2rem;
        height: 2.2rem;
        flex: 0 0 auto;
        place-items: center;
        overflow: hidden;
        border-radius: 50%;
        background: #f2e6e8;
        color: #7f1d2d;
        font-weight: 700;
        object-fit: cover;
    }

    .student-sport-status-badge {
        display: inline-block;
        border-radius: 99px;
        padding: 0.25rem 0.55rem;
        background: #edf7ef;
        color: #256239;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .student-sport-pagination {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-top: 1rem;
        color: #64595b;
        font-size: 0.85rem;
    }

    .student-sport-pagination button {
        border: 1px solid #ddd4d6;
        border-radius: 0.5rem;
        padding: 0.45rem 0.7rem;
        background: #fff;
        color: #7f1d2d;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    .student-sport-pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .student-sport-member-profile {
        border: 0;
        padding: 0;
        background: transparent;
        color: #33282a;
        font: inherit;
        font-weight: 700;
        text-align: left;
        cursor: pointer;
    }

    .student-sport-member-profile:hover,
    .student-sport-member-profile:focus-visible {
        color: #8b1e2d;
        text-decoration: underline;
        text-underline-offset: 0.15em;
    }

    .student-athlete-profile-view {
        padding: 1rem 1.5rem 1.5rem;
    }

    .student-athlete-profile-actions {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
    }

    .student-athlete-back,
    .student-athlete-close {
        border: 1px solid #e5dcde;
        border-radius: 0.55rem;
        padding: 0.55rem 0.75rem;
        background: #fff;
        color: #7f1d2d;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    .student-athlete-close {
        width: 2.4rem;
        height: 2.4rem;
        padding: 0;
        border-radius: 50%;
        color: #3a3032;
        font-size: 1.5rem;
        line-height: 1;
    }

    .student-athlete-profile-message {
        margin: 1.5rem 0;
        color: #64595b;
    }

    .student-athlete-profile-message.is-error {
        color: #a11d2c;
    }

    .student-athlete-profile-identity {
        display: flex;
        align-items: center;
        gap: 1rem;
        margin: 1.25rem 0;
        padding: 1rem;
        border: 1px solid #eee7e8;
        border-radius: 0.8rem;
        background: #fcf9fa;
    }

    .student-athlete-profile-photo,
    .student-athlete-profile-initial {
        display: inline-grid;
        width: 5rem;
        height: 5rem;
        flex: 0 0 auto;
        place-items: center;
        overflow: hidden;
        border-radius: 50%;
        background: #f2e6e8;
        color: #7f1d2d;
        font-size: 1.8rem;
        font-weight: 800;
        object-fit: cover;
    }

    .student-athlete-profile-identity h2 {
        margin: 0.2rem 0;
        color: #302528;
        font-size: clamp(1.25rem, 4vw, 1.7rem);
        overflow-wrap: anywhere;
    }

    .student-athlete-profile-id {
        margin: 0.2rem 0 0.5rem;
        color: #64595b;
    }

    .student-athlete-profile-status {
        display: inline-block;
        border-radius: 99px;
        padding: 0.25rem 0.6rem;
        background: #edf7ef;
        color: #256239;
        font-size: 0.75rem;
        font-weight: 700;
    }

    .student-athlete-profile-fields {
        display: grid;
        gap: 1rem;
    }

    .student-athlete-profile-fields section {
        border: 1px solid #eee7e8;
        border-radius: 0.75rem;
        padding: 1rem;
    }

    .student-athlete-profile-fields h3 {
        margin: 0 0 0.75rem;
        color: #7f1d2d;
        font-size: 1rem;
    }

    .student-athlete-profile-fields dl {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 0.75rem 1rem;
        margin: 0;
    }

    .student-athlete-profile-fields dl > div {
        min-width: 0;
    }

    .student-athlete-profile-fields dt {
        margin-bottom: 0.2rem;
        color: #766c6e;
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .student-athlete-profile-fields dd {
        margin: 0;
        color: #302528;
        overflow-wrap: anywhere;
    }

    @keyframes student-sport-fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes student-sport-slide-in {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }

    @media (max-width: 600px) {
        .student-sport-modal { padding: 0.5rem; }
        .student-sport-dialog { max-height: 94vh; border-radius: 0.8rem; }
        .student-sport-modal-header,
        .student-sport-roster { padding-right: 1rem; padding-left: 1rem; }
        .student-sport-summary { grid-template-columns: 1fr; padding-right: 1rem; padding-left: 1rem; }
        .student-sport-filters { grid-template-columns: 1fr; }
        .student-sport-table th,
        .student-sport-table td { padding: 0.6rem; }
        .student-athlete-profile-view { padding: 0.75rem 1rem 1rem; }
        .student-athlete-profile-identity { align-items: flex-start; padding: 0.75rem; }
        .student-athlete-profile-photo,
        .student-athlete-profile-initial { width: 3.75rem; height: 3.75rem; font-size: 1.4rem; }
        .student-athlete-profile-fields dl { grid-template-columns: 1fr; }
    }

    @media (prefers-reduced-motion: reduce) {
        .student-sport-card,
        .student-sport-modal,
        .student-sport-dialog { animation: none; transition: none; }
    }
</style>

<script>
    (() => {
        const grid = document.querySelector('.student-sports-grid');
        const modal = document.getElementById('student-sport-modal');
        if (!grid || !modal || modal.dataset.initialized === 'true') return;
        modal.dataset.initialized = 'true';

        const dialog = modal.querySelector('.student-sport-dialog');
        const programView = modal.querySelector('.student-sport-program-view');
        const profileView = modal.querySelector('.student-athlete-profile-view');
        const title = modal.querySelector('#student-sport-modal-title');
        const description = modal.querySelector('.student-sport-modal-description');
        const classification = modal.querySelector('.student-sport-modal-classification');
        const total = modal.querySelector('.student-sport-total');
        const coach = modal.querySelector('.student-sport-coach-name');
        const pending = modal.querySelector('.student-sport-pending');
        const searchInput = modal.querySelector('.student-sport-search');
        const gradeSelect = modal.querySelector('.student-sport-grade');
        const statusSelect = modal.querySelector('.student-sport-status');
        const message = modal.querySelector('.student-sport-message');
        const tableWrap = modal.querySelector('.student-sport-table-wrap');
        const tableBody = modal.querySelector('.student-sport-table tbody');
        const pagination = modal.querySelector('.student-sport-pagination');
        const previousButton = modal.querySelector('.student-sport-previous');
        const nextButton = modal.querySelector('.student-sport-next');
        const pageLabel = modal.querySelector('.student-sport-page-label');
        const profileMessage = modal.querySelector('.student-athlete-profile-message');
        const profileContent = modal.querySelector('.student-athlete-profile-content');
        const profilePhoto = modal.querySelector('.student-athlete-profile-photo');
        const profileInitial = modal.querySelector('.student-athlete-profile-initial');
        const profileName = modal.querySelector('#student-athlete-profile-name');
        const profileStudentId = modal.querySelector('.student-athlete-profile-id');
        const profileStatus = modal.querySelector('.student-athlete-profile-status');
        let activeUrl = null;
        let currentPage = 1;
        let requestController = null;
        let profileRequestController = null;
        let searchTimer = null;
        let returnFocusTo = null;
        let profileReturnFocusTo = null;

        const setMessage = (text, isError = false) => {
            message.textContent = text;
            message.classList.toggle('is-error', isError);
            message.hidden = false;
            tableWrap.hidden = true;
            pagination.hidden = true;
        };

        const addCell = (row, text) => {
            const cell = document.createElement('td');
            cell.textContent = text || '—';
            row.appendChild(cell);
            return cell;
        };

        const renderMembers = (members) => {
            tableBody.replaceChildren();
            members.forEach((student) => {
                const row = document.createElement('tr');
                const studentCell = document.createElement('td');
                const studentInfo = document.createElement('span');
                studentInfo.className = 'student-sport-student';

                if (student.photo_url) {
                    const image = document.createElement('img');
                    image.className = 'student-sport-avatar';
                    image.src = student.photo_url;
                    image.alt = '';
                    image.loading = 'lazy';
                    image.onerror = () => {
                        const fallback = document.createElement('span');
                        fallback.className = 'student-sport-avatar-fallback';
                        fallback.textContent = student.name.trim().charAt(0).toUpperCase() || '?';
                        image.replaceWith(fallback);
                    };
                    studentInfo.appendChild(image);
                } else {
                    const fallback = document.createElement('span');
                    fallback.className = 'student-sport-avatar-fallback';
                    fallback.textContent = student.name.trim().charAt(0).toUpperCase() || '?';
                    studentInfo.appendChild(fallback);
                }

                const name = document.createElement('button');
                name.type = 'button';
                name.className = 'student-sport-member-profile';
                name.textContent = student.name;
                name.dataset.profileUrl = student.profile_url;
                name.setAttribute('aria-label', `View ${student.name}'s athlete profile`);
                studentInfo.appendChild(name);
                studentCell.appendChild(studentInfo);
                row.appendChild(studentCell);
                addCell(row, student.student_id);
                addCell(row, student.grade);

                const statusCell = document.createElement('td');
                const badge = document.createElement('span');
                badge.className = 'student-sport-status-badge';
                badge.textContent = student.enrollment_status;
                statusCell.appendChild(badge);
                row.appendChild(statusCell);
                tableBody.appendChild(row);
            });
        };

        const loadMembers = async (page = 1) => {
            if (!activeUrl) return;
            if (requestController) requestController.abort();
            requestController = new AbortController();
            currentPage = page;

            const url = new URL(activeUrl, window.location.origin);
            const term = searchInput.value.trim();
            if (term) url.searchParams.set('search', term);
            if (gradeSelect.value) url.searchParams.set('grade', gradeSelect.value);
            if (statusSelect.value) url.searchParams.set('status', statusSelect.value);
            url.searchParams.set('page', String(page));
            setMessage('Loading enrolled students...');

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: requestController.signal,
                });
                if (!response.ok) throw new Error(response.status === 403 ? 'You are not authorized to view this roster.' : 'Unable to load members right now. Please try again.');

                const data = await response.json();
                title.textContent = data.program.name;
                description.textContent = data.program.description || 'Program details will be available soon.';
                classification.textContent = data.program.classification || 'Sports program';
                total.textContent = String(data.total_members);
                coach.textContent = data.program.coaches.length ? data.program.coaches.join(', ') : 'Not assigned';
                pending.textContent = String(data.pending_applications);

                const selectedGrade = gradeSelect.value;
                gradeSelect.replaceChildren(new Option('All grades', ''));
                data.grades.forEach((grade) => gradeSelect.add(new Option(grade, grade)));
                gradeSelect.value = selectedGrade;

                if (data.members.total === 0) {
                    setMessage(data.total_members === 0
                        ? 'No students have enrolled in this program yet.'
                        : 'No students match your search or filters.');
                    return;
                }

                message.hidden = true;
                tableWrap.hidden = false;
                renderMembers(data.members.data);
                pagination.hidden = data.members.last_page <= 1;
                previousButton.disabled = data.members.current_page <= 1;
                nextButton.disabled = data.members.current_page >= data.members.last_page;
                pageLabel.textContent = `Page ${data.members.current_page} of ${data.members.last_page}`;
            } catch (error) {
                if (error.name === 'AbortError') return;
                setMessage(error.message || 'Unable to load members right now. Please try again.', true);
            }
        };

        const openModal = (card) => {
            activeUrl = card.dataset.membersUrl;
            returnFocusTo = card;
            profileView.hidden = true;
            programView.hidden = false;
            dialog.setAttribute('aria-labelledby', 'student-sport-modal-title');
            currentPage = 1;
            searchInput.value = '';
            gradeSelect.value = '';
            statusSelect.value = '';
            modal.hidden = false;
            document.body.style.overflow = 'hidden';
            dialog.focus();
            loadMembers();
        };

        const showProfileMessage = (text, isError = false) => {
            profileMessage.textContent = text;
            profileMessage.classList.toggle('is-error', isError);
            profileMessage.hidden = false;
            profileContent.hidden = true;
        };

        const loadProfile = async (button) => {
            if (profileRequestController) profileRequestController.abort();
            profileRequestController = new AbortController();
            profileReturnFocusTo = button;
            programView.hidden = true;
            profileView.hidden = false;
            dialog.setAttribute('aria-labelledby', 'student-athlete-profile-name');
            showProfileMessage('Loading athlete profile...');
            profileView.querySelector('.student-athlete-back').focus();

            try {
                const response = await fetch(button.dataset.profileUrl, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    signal: profileRequestController.signal,
                });
                if (!response.ok) throw new Error(response.status === 404
                    ? 'This athlete is no longer an active member of this program.'
                    : 'Unable to load this athlete profile. Please try again.');

                const { profile } = await response.json();
                profileName.textContent = profile.name;
                profileStudentId.textContent = profile.student_id || 'Student ID not provided';
                profileStatus.textContent = profile.status;
                profileInitial.textContent = profile.name.trim().charAt(0).toUpperCase() || '?';
                profilePhoto.hidden = !profile.photo_url;
                profileInitial.hidden = Boolean(profile.photo_url);
                if (profile.photo_url) {
                    profilePhoto.src = profile.photo_url;
                    profilePhoto.onerror = () => {
                        profilePhoto.hidden = true;
                        profileInitial.hidden = false;
                    };
                } else {
                    profilePhoto.removeAttribute('src');
                }

                const fieldValues = {
                    grade: profile.grade || 'Not recorded',
                    status: profile.status || 'Not recorded',
                    sport: profile.sport || 'Not recorded',
                    position: 'Not recorded',
                    team: 'Not recorded',
                    coach: profile.coaches.length ? profile.coaches.join(', ') : 'Not assigned',
                    jersey: 'Not recorded',
                    date_joined: profile.date_joined || 'Not recorded',
                };
                Object.entries(fieldValues).forEach(([field, value]) => {
                    const element = profileView.querySelector(`[data-profile-field="${field}"]`);
                    element.textContent = value;
                });

                profileMessage.hidden = true;
                profileContent.hidden = false;
                profileView.querySelector('.student-athlete-back').focus();
            } catch (error) {
                if (error.name === 'AbortError') return;
                showProfileMessage(error.message || 'Unable to load this athlete profile. Please try again.', true);
            }
        };

        const backToMembers = () => {
            if (profileRequestController) profileRequestController.abort();
            profileRequestController = null;
            profileView.hidden = true;
            programView.hidden = false;
            dialog.setAttribute('aria-labelledby', 'student-sport-modal-title');
            if (profileReturnFocusTo) profileReturnFocusTo.focus();
        };

        const closeModal = () => {
            if (requestController) requestController.abort();
            if (profileRequestController) profileRequestController.abort();
            requestController = null;
            profileRequestController = null;
            modal.hidden = true;
            document.body.style.overflow = '';
            activeUrl = null;
            profileView.hidden = true;
            programView.hidden = false;
            dialog.setAttribute('aria-labelledby', 'student-sport-modal-title');
            if (returnFocusTo) returnFocusTo.focus();
        };

        tableBody.addEventListener('click', (event) => {
            const button = event.target.closest('.student-sport-member-profile');
            if (button) loadProfile(button);
        });

        grid.addEventListener('click', (event) => {
            if (event.target.closest('a, button, input, select, textarea, [contenteditable="true"]')) return;
            const card = event.target.closest('.student-sport-card[data-members-url]');
            if (card) openModal(card);
        });

        grid.addEventListener('keydown', (event) => {
            if (!['Enter', ' '].includes(event.key) || event.target !== event.currentTarget && event.target.closest('a, button, input, select, textarea, [contenteditable="true"]')) return;
            const card = event.target.closest('.student-sport-card[data-members-url]');
            if (!card) return;
            event.preventDefault();
            openModal(card);
        });

        modal.querySelector('.student-sport-modal-close').addEventListener('click', closeModal);
        modal.querySelector('.student-athlete-back').addEventListener('click', backToMembers);
        modal.querySelector('.student-athlete-close').addEventListener('click', closeModal);
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });
        document.addEventListener('keydown', (event) => {
            if (modal.hidden) return;
            if (event.key === 'Escape') {
                if (!profileView.hidden) backToMembers();
                else closeModal();
            }
            if (event.key === 'Tab') {
                const focusable = [...dialog.querySelectorAll('button:not(:disabled), input:not(:disabled), select:not(:disabled)')]
                    .filter((element) => !element.closest('[hidden]'));
                if (!focusable.length) return;
                const first = focusable[0];
                const last = focusable[focusable.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });

        searchInput.addEventListener('input', () => {
            window.clearTimeout(searchTimer);
            searchTimer = window.setTimeout(() => loadMembers(1), 250);
        });
        gradeSelect.addEventListener('change', () => loadMembers(1));
        statusSelect.addEventListener('change', () => loadMembers(1));
        previousButton.addEventListener('click', () => loadMembers(currentPage - 1));
        nextButton.addEventListener('click', () => loadMembers(currentPage + 1));
    })();
</script>
