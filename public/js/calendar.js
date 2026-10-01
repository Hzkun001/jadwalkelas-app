/**
 * Jadwal Kelas App - Interactive Timetable & Calendar
 * Ultra-lightweight Vanilla JS with Day/Agenda, Week, and Month Views
 */

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('timetable-calendar');
    if (!calendarEl) return;

    // Default to 'day' on mobile (< 768px), 'week' on desktop
    let currentMode = window.innerWidth < 768 ? 'day' : 'week';
    let currentDate = new Date(); // anchor date
    let selectedRoomId = parseInt(new URLSearchParams(window.location.search).get('room') || '0', 10);
    let cachedEvents = [];
    let selectedDayOfWeek = currentDate.getDay() === 0 ? 1 : currentDate.getDay(); // 1 (Mon) .. 6 (Sat)
    if (selectedDayOfWeek > 6) selectedDayOfWeek = 1;

    // DOM Elements
    const titleEl = document.getElementById('calendar-title');
    const prevBtn = document.getElementById('btn-prev');
    const nextBtn = document.getElementById('btn-next');
    const todayBtn = document.getElementById('btn-today');
    const modeDayBtn = document.getElementById('btn-mode-day');
    const modeWeekBtn = document.getElementById('btn-mode-week');
    const modeMonthBtn = document.getElementById('btn-mode-month');
    const roomSelect = document.getElementById('room-filter-select');
    const detailModal = document.getElementById('event-detail-modal');
    const modalClose = document.getElementById('modal-close-btn');
    const dayTabsContainer = document.getElementById('day-tabs-container');
    const dayTabs = document.querySelectorAll('.day-tab');

    // Setup day tabs
    dayTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            selectedDayOfWeek = parseInt(this.getAttribute('data-day'), 10);
            updateDayTabs();
            // Sync currentDate to this day of the current week
            const range = getWeekRange(currentDate);
            const targetDate = new Date(range.start);
            targetDate.setDate(targetDate.getDate() + (selectedDayOfWeek - 1));
            currentDate = targetDate;
            fetchAndRender();
        });
    });

    function updateDayTabs() {
        if (!dayTabsContainer) return;
        if (currentMode === 'day') {
            dayTabsContainer.classList.remove('hidden');
            dayTabs.forEach(tab => {
                const day = parseInt(tab.getAttribute('data-day'), 10);
                if (day === selectedDayOfWeek) {
                    tab.className = 'day-tab px-3.5 py-1.5 rounded-xl text-xs font-black border-2 transition-all bg-indigo-600 text-white border-indigo-600 shadow-xs';
                } else {
                    tab.className = 'day-tab px-3.5 py-1.5 rounded-xl text-xs font-black border-2 transition-all bg-white text-slate-800 border-slate-200 hover:bg-slate-50';
                }
            });
        } else {
            dayTabsContainer.classList.add('hidden');
        }
    }

    // Event listeners
    if (prevBtn) prevBtn.addEventListener('click', () => navigateDate(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => navigateDate(1));
    if (todayBtn) todayBtn.addEventListener('click', () => { 
        currentDate = new Date(); 
        selectedDayOfWeek = currentDate.getDay() === 0 ? 1 : Math.min(currentDate.getDay(), 6);
        updateDayTabs();
        fetchAndRender(); 
    });

    if (modeDayBtn) {
        modeDayBtn.addEventListener('click', () => {
            currentMode = 'day';
            updateModeButtons();
            updateDayTabs();
            fetchAndRender();
        });
    }

    if (modeWeekBtn) {
        modeWeekBtn.addEventListener('click', () => {
            currentMode = 'week';
            updateModeButtons();
            updateDayTabs();
            fetchAndRender();
        });
    }

    if (modeMonthBtn) {
        modeMonthBtn.addEventListener('click', () => {
            currentMode = 'month';
            updateModeButtons();
            updateDayTabs();
            fetchAndRender();
        });
    }

    if (roomSelect) {
        roomSelect.value = selectedRoomId;
        roomSelect.addEventListener('change', (e) => {
            selectedRoomId = parseInt(e.target.value, 10);
            fetchAndRender();
        });
    }

    if (modalClose) {
        modalClose.addEventListener('click', () => {
            detailModal.classList.add('hidden');
        });
    }

    if (detailModal) {
        detailModal.addEventListener('click', (e) => {
            if (e.target === detailModal) detailModal.classList.add('hidden');
        });
    }

    function updateModeButtons() {
        const activeClass = 'px-3.5 py-1.5 text-xs font-black rounded-xl bg-indigo-600 text-white shadow-xs transition-all';
        const inactiveClass = 'px-3.5 py-1.5 text-xs font-black rounded-xl text-slate-700 hover:bg-white transition-all';

        if (modeDayBtn) modeDayBtn.className = currentMode === 'day' ? activeClass : inactiveClass;
        if (modeWeekBtn) modeWeekBtn.className = currentMode === 'week' ? activeClass : inactiveClass;
        if (modeMonthBtn) modeMonthBtn.className = currentMode === 'month' ? activeClass : inactiveClass;
    }

    function navigateDate(direction) {
        if (currentMode === 'day') {
            currentDate.setDate(currentDate.getDate() + direction);
            selectedDayOfWeek = currentDate.getDay() === 0 ? 1 : Math.min(currentDate.getDay(), 6);
            updateDayTabs();
        } else if (currentMode === 'week') {
            currentDate.setDate(currentDate.getDate() + (direction * 7));
        } else {
            currentDate.setMonth(currentDate.getMonth() + direction);
        }
        fetchAndRender();
    }

    function formatDate(d) {
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function getWeekRange(date) {
        const d = new Date(date);
        const day = d.getDay();
        const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Monday is start
        const monday = new Date(d.setDate(diff));
        const saturday = new Date(monday);
        saturday.setDate(monday.getDate() + 5); // Monday to Saturday
        return { start: monday, end: saturday };
    }

    function getMonthRange(date) {
        const start = new Date(date.getFullYear(), date.getMonth(), 1);
        const end = new Date(date.getFullYear(), date.getMonth() + 1, 0);
        return { start, end };
    }

    async function fetchAndRender() {
        let range;
        if (currentMode === 'day') {
            range = { start: new Date(currentDate), end: new Date(currentDate) };
        } else if (currentMode === 'week') {
            range = getWeekRange(currentDate);
        } else {
            range = getMonthRange(currentDate);
        }

        const startStr = formatDate(range.start);
        const endStr = formatDate(range.end);

        // Update header title
        if (titleEl) {
            const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
            if (currentMode === 'day') {
                const dayName = dayNames[currentDate.getDay()];
                titleEl.textContent = `${dayName}, ${currentDate.getDate()} ${currentDate.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' })}`;
            } else if (currentMode === 'week') {
                const opt = { day: 'numeric', month: 'short' };
                titleEl.textContent = `${range.start.toLocaleDateString('id-ID', opt)} - ${range.end.toLocaleDateString('id-ID', opt)} ${range.end.getFullYear()}`;
            } else {
                titleEl.textContent = currentDate.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            }
        }

        try {
            calendarEl.innerHTML = '<div class="p-12 text-center text-slate-500 font-bold text-sm">Memuat data jadwal perkuliahan...</div>';
            const url = `/api/events?start=${startStr}&end=${endStr}&room_id=${selectedRoomId}`;
            const res = await fetch(url);
            const json = await res.json();

            if (json.success) {
                cachedEvents = json.data;
                if (currentMode === 'day') {
                    renderDayView(currentDate, cachedEvents);
                } else if (currentMode === 'week') {
                    renderWeekView(range, cachedEvents);
                } else {
                    renderMonthView(range, cachedEvents);
                }
            } else {
                calendarEl.innerHTML = '<div class="p-10 text-center text-rose-600 font-bold">Gagal memuat data jadwal</div>';
            }
        } catch (err) {
            console.error(err);
            calendarEl.innerHTML = '<div class="p-10 text-center text-rose-600 font-bold">Terjadi gangguan koneksi</div>';
        }
    }

    /**
     * Mode Harian / Agenda: Super ramah layar HP (Mobile-First)
     */
    function renderDayView(targetDate, events) {
        const dateStr = formatDate(targetDate);
        const dayEvents = events.filter(e => e.start.split('T')[0] === dateStr);

        // Sort events chronologically by start time
        dayEvents.sort((a, b) => a.start.localeCompare(b.start));

        if (dayEvents.length === 0) {
            calendarEl.innerHTML = `
                <div class="p-12 text-center space-y-3">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-2xl font-bold">
                        🎉
                    </div>
                    <h3 class="text-xl font-black text-slate-900">Semua Ruangan Kosong & Bebas</h3>
                    <p class="text-sm font-semibold text-slate-600 max-w-md mx-auto">
                        Tidak ada perkuliahan reguler maupun peminjaman acara yang tercatat untuk hari ini. Ruangan dapat digunakan untuk belajar bersama atau kegiatan organisasi.
                    </p>
                    <div class="pt-2">
                        <a href="/booking/create" class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-black rounded-xl shadow-xs transition-colors">
                            Pinjam Ruang Sekarang
                        </a>
                    </div>
                </div>
            `;
            return;
        }

        let html = `
            <div class="p-4 sm:p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b-2 border-slate-100">
                    <span class="text-xs font-black text-slate-600 uppercase tracking-wider">
                        DAFTAR PERKULIAHAN & KEGIATAN HARI INI (${dayEvents.length} JADWAL)
                    </span>
                    <span class="text-xs font-extrabold text-slate-500">Urut berdasarkan jam</span>
                </div>

                <div class="space-y-3">
        `;

        dayEvents.forEach(e => {
            const startTime = e.start.split('T')[1];
            const endTime = e.end.split('T')[1];
            const isLecture = e.type === 'lecture';
            const badgeClass = isLecture ? 'bg-blue-600 text-white' : 'bg-amber-500 text-slate-950';
            const borderAccent = isLecture ? 'border-l-blue-600' : 'border-l-amber-500';
            const bgContainer = isLecture ? 'bg-blue-50/40 hover:bg-blue-50/80 border-blue-200' : 'bg-amber-50/40 hover:bg-amber-50/80 border-amber-200';

            html += `
                <div class="p-4 sm:p-5 rounded-2xl border-2 ${bgContainer} border-l-[6px] ${borderAccent} transition-all cursor-pointer shadow-2xs hover:shadow-xs"
                     data-event-id="${e.id}">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mb-2">
                        <div class="flex items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-black uppercase tracking-wider ${badgeClass}">
                                ${isLecture ? 'KULIAH' : 'ACARA'}
                            </span>
                            <span class="text-xs font-bold text-slate-700 bg-white px-2.5 py-0.5 rounded-md border border-slate-200">
                                🏢 ${e.room_name}
                            </span>
                        </div>
                        <div class="text-sm font-black font-mono text-slate-900 bg-white px-3 py-1 rounded-lg border border-slate-200 self-start sm:self-auto">
                            ⏱️ ${startTime} - ${endTime} WIB
                        </div>
                    </div>

                    <div class="space-y-1">
                        <h3 class="text-lg font-black text-slate-900 leading-tight">${e.title}</h3>
                        ${isLecture ? `
                            <div class="text-xs font-semibold text-slate-700 flex flex-wrap items-center gap-x-3 gap-y-1 pt-1">
                                <span>Kelas: <strong class="text-slate-900">${e.class_name || '-'}</strong></span>
                                <span>&bull;</span>
                                <span>Dosen: <strong class="text-slate-900">${e.lecturer_name || '-'}</strong></span>
                            </div>
                        ` : `
                            <div class="text-xs font-semibold text-slate-700 pt-1">
                                Penyelenggara: <strong class="text-slate-900">${e.organizer || '-'}</strong>
                            </div>
                        `}
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        calendarEl.innerHTML = html;
        attachEventClickHandlers(events);
    }

    /**
     * Mode Mingguan (Weekly Timetable)
     */
    function renderWeekView(range, events) {
        const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const dates = [];
        for (let i = 0; i < 6; i++) {
            const d = new Date(range.start);
            d.setDate(d.getDate() + i);
            dates.push(d);
        }

        let html = `
        <div class="overflow-x-auto">
            <div class="min-w-[850px]">
                <!-- Header Days -->
                <div class="grid grid-cols-7 border-b-2 border-slate-200 bg-slate-100 text-center text-xs font-black text-slate-800">
                    <div class="py-3 px-2 border-r-2 border-slate-200 text-slate-500">WAKTU</div>
                    ${dates.map((d, idx) => `
                        <div class="py-3 px-2 border-r-2 border-slate-200 last:border-r-0">
                            <div class="text-sm font-black text-slate-900">${days[idx]}</div>
                            <div class="text-xs font-bold text-slate-500">${d.getDate()} ${d.toLocaleDateString('id-ID', { month: 'short' })}</div>
                        </div>
                    `).join('')}
                </div>

                <!-- Hours Grid (07:00 to 18:00) -->
                <div class="relative divide-y divide-slate-100">
        `;

        for (let h = 7; h <= 17; h++) {
            const timeLabel = `${String(h).padStart(2, '0')}:00`;
            html += `
                <div class="grid grid-cols-7 min-h-[68px] border-b border-slate-200 last:border-b-0">
                    <div class="p-2 border-r-2 border-slate-200 text-xs font-mono font-bold text-slate-600 text-right pr-3 select-none bg-slate-50/50">
                        ${timeLabel}
                    </div>
                    ${dates.map((d) => {
                        const dateStr = formatDate(d);
                        const cellEvents = events.filter(e => {
                            const eDate = e.start.split('T')[0];
                            const eTime = e.start.split('T')[1];
                            const eHour = parseInt(eTime.split(':')[0], 10);
                            return eDate === dateStr && eHour === h;
                        });

                        return `
                            <div class="p-1 border-r border-slate-200 last:border-r-0 relative group hover:bg-slate-50 transition-colors">
                                ${cellEvents.map(e => `
                                    <div class="mb-1 p-2 rounded-xl text-white shadow-2xs cursor-pointer hover:brightness-95 transition-all text-xs border border-white/20"
                                         style="background-color: ${e.color};"
                                         data-event-id="${e.id}">
                                        <div class="font-black truncate leading-tight">${e.title}</div>
                                        <div class="text-[11px] font-bold opacity-95 truncate">${e.start.split('T')[1]} - ${e.end.split('T')[1]}</div>
                                        <div class="text-[10px] font-semibold opacity-90 truncate">${e.room_name}</div>
                                    </div>
                                `).join('')}
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }

        html += `
                </div>
            </div>
        </div>
        `;

        calendarEl.innerHTML = html;
        attachEventClickHandlers(events);
    }

    /**
     * Mode Bulanan (Month View)
     */
    function renderMonthView(range, events) {
        const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        const firstDayIndex = range.start.getDay();
        const totalDays = range.end.getDate();

        let html = `
        <div class="overflow-x-auto">
            <div class="min-w-[700px]">
                <div class="grid grid-cols-7 border-b-2 border-slate-200 bg-slate-100 text-center text-xs font-black text-slate-800">
                    ${days.map(d => `<div class="py-2.5">${d}</div>`).join('')}
                </div>
                <div class="grid grid-cols-7 border-l-2 border-t-2 border-slate-200">
        `;

        // Empty cells before start
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div class="min-h-[105px] bg-slate-50/50 border-r border-b border-slate-200 p-2 text-slate-300"></div>`;
        }

        // Days of month
        for (let d = 1; d <= totalDays; d++) {
            const thisDate = new Date(range.start.getFullYear(), range.start.getMonth(), d);
            const dateStr = formatDate(thisDate);
            const dayEvents = events.filter(e => e.start.split('T')[0] === dateStr);
            const isToday = dateStr === formatDate(new Date());

            html += `
                <div class="min-h-[105px] border-r border-b border-slate-200 p-2 hover:bg-slate-50 transition-colors ${isToday ? 'bg-indigo-50/40' : ''}">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-black ${isToday ? 'w-6 h-6 flex items-center justify-center bg-indigo-600 text-white rounded-full' : 'text-slate-800'}">${d}</span>
                        ${dayEvents.length > 0 ? `<span class="text-[11px] font-black text-slate-600">${dayEvents.length}</span>` : ''}
                    </div>
                    <div class="space-y-1">
                        ${dayEvents.slice(0, 3).map(e => `
                            <div class="text-[10px] font-bold truncate px-1.5 py-0.5 rounded-md text-white cursor-pointer hover:opacity-90"
                                 style="background-color: ${e.color}"
                                 data-event-id="${e.id}">
                                ${e.start.split('T')[1]} ${e.title}
                            </div>
                        `).join('')}
                        ${dayEvents.length > 3 ? `<div class="text-[10px] text-slate-700 font-extrabold">+${dayEvents.length - 3} lainnya</div>` : ''}
                    </div>
                </div>
            `;
        }

        html += `
                </div>
            </div>
        </div>
        `;

        calendarEl.innerHTML = html;
        attachEventClickHandlers(events);
    }

    function attachEventClickHandlers(events) {
        document.querySelectorAll('[data-event-id]').forEach(el => {
            el.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = el.getAttribute('data-event-id');
                const eventItem = events.find(item => String(item.id) === String(id));
                if (eventItem) showEventDetailModal(eventItem);
            });
        });
    }

    function showEventDetailModal(ev) {
        if (!detailModal) return;

        const title = document.getElementById('modal-title');
        const badge = document.getElementById('modal-badge');
        const time = document.getElementById('modal-time');
        const room = document.getElementById('modal-room');
        const extraLabel = document.getElementById('modal-extra-label');
        const extraValue = document.getElementById('modal-extra-value');

        if (title) title.textContent = ev.title;
        if (time) {
            const startTime = ev.start.split('T')[1];
            const endTime = ev.end.split('T')[1];
            const dateStr = ev.start.split('T')[0];
            time.innerHTML = `${dateStr} &bull; <span class="font-mono font-black">${startTime} - ${endTime}</span> WIB`;
        }
        if (room) room.textContent = ev.room_name;

        if (badge) {
            if (ev.type === 'lecture') {
                badge.className = 'inline-block px-3 py-1 text-xs font-black rounded-xl bg-blue-600 text-white shadow-xs';
                badge.textContent = 'Perkuliahan Reguler (Prioritas #1)';
            } else {
                badge.className = 'inline-block px-3 py-1 text-xs font-black rounded-xl bg-amber-500 text-slate-950 shadow-xs';
                badge.textContent = 'Peminjaman Acara Ormawa';
            }
        }

        if (extraLabel && extraValue) {
            if (ev.type === 'lecture') {
                extraLabel.textContent = 'Dosen & Kelas:';
                extraValue.textContent = `${ev.lecturer_name || '-'} (Kelas: ${ev.class_name || '-'})`;
            } else {
                extraLabel.textContent = 'Penyelenggara Kegiatan:';
                extraValue.textContent = ev.organizer || '-';
            }
        }

        detailModal.classList.remove('hidden');
    }

    // Initialize buttons & data
    updateModeButtons();
    updateDayTabs();
    fetchAndRender();
});
