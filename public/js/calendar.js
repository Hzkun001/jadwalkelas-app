/**
 * Jadwal Kelas App - Interactive Timetable & Calendar
 * Ultra-lightweight Vanilla JS (Zero heavy dependencies)
 */

document.addEventListener('DOMContentLoaded', function () {
    const calendarEl = document.getElementById('timetable-calendar');
    if (!calendarEl) return;

    let currentMode = 'week'; // 'week' or 'month'
    let currentDate = new Date(); // anchor date
    let selectedRoomId = parseInt(new URLSearchParams(window.location.search).get('room') || '0', 10);
    let cachedEvents = [];

    // DOM Elements
    const titleEl = document.getElementById('calendar-title');
    const prevBtn = document.getElementById('btn-prev');
    const nextBtn = document.getElementById('btn-next');
    const todayBtn = document.getElementById('btn-today');
    const modeWeekBtn = document.getElementById('btn-mode-week');
    const modeMonthBtn = document.getElementById('btn-mode-month');
    const roomSelect = document.getElementById('room-filter-select');
    const detailModal = document.getElementById('event-detail-modal');
    const modalClose = document.getElementById('modal-close-btn');

    // Event listeners
    if (prevBtn) prevBtn.addEventListener('click', () => navigateDate(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => navigateDate(1));
    if (todayBtn) todayBtn.addEventListener('click', () => { currentDate = new Date(); fetchAndRender(); });

    if (modeWeekBtn) {
        modeWeekBtn.addEventListener('click', () => {
            currentMode = 'week';
            updateModeButtons();
            fetchAndRender();
        });
    }

    if (modeMonthBtn) {
        modeMonthBtn.addEventListener('click', () => {
            currentMode = 'month';
            updateModeButtons();
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
        if (!modeWeekBtn || !modeMonthBtn) return;
        if (currentMode === 'week') {
            modeWeekBtn.className = 'px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white shadow-xs';
            modeMonthBtn.className = 'px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:bg-slate-100';
        } else {
            modeMonthBtn.className = 'px-3 py-1.5 text-xs font-bold rounded-lg bg-indigo-600 text-white shadow-xs';
            modeWeekBtn.className = 'px-3 py-1.5 text-xs font-bold rounded-lg text-slate-600 hover:bg-slate-100';
        }
    }

    function navigateDate(direction) {
        if (currentMode === 'week') {
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
        if (currentMode === 'week') {
            range = getWeekRange(currentDate);
        } else {
            range = getMonthRange(currentDate);
        }

        const startStr = formatDate(range.start);
        const endStr = formatDate(range.end);

        // Update header title
        if (titleEl) {
            if (currentMode === 'week') {
                const opt = { day: 'numeric', month: 'short' };
                titleEl.textContent = `${range.start.toLocaleDateString('id-ID', opt)} - ${range.end.toLocaleDateString('id-ID', opt)} ${range.end.getFullYear()}`;
            } else {
                titleEl.textContent = currentDate.toLocaleDateString('id-ID', { month: 'long', year: 'numeric' });
            }
        }

        try {
            calendarEl.innerHTML = '<div class="p-12 text-center text-slate-400">Memuat jadwal...</div>';
            const url = `/api/events?start=${startStr}&end=${endStr}&room_id=${selectedRoomId}`;
            const res = await fetch(url);
            const json = await res.json();

            if (json.success) {
                cachedEvents = json.data;
                if (currentMode === 'week') {
                    renderWeekView(range, cachedEvents);
                } else {
                    renderMonthView(range, cachedEvents);
                }
            } else {
                calendarEl.innerHTML = '<div class="p-8 text-center text-rose-500">Gagal memuat jadwal</div>';
            }
        } catch (err) {
            console.error(err);
            calendarEl.innerHTML = '<div class="p-8 text-center text-rose-500">Terjadi kesalahan koneksi</div>';
        }
    }

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
            <div class="min-w-[800px]">
                <!-- Header Days -->
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-100/70 text-center text-xs font-bold text-slate-700">
                    <div class="py-3 px-2 border-r border-slate-200 text-slate-400">Waktu</div>
                    ${dates.map((d, idx) => `
                        <div class="py-3 px-2 border-r border-slate-200 last:border-r-0">
                            <div>${days[idx]}</div>
                            <div class="text-[11px] font-normal text-slate-500">${d.getDate()} ${d.toLocaleDateString('id-ID', { month: 'short' })}</div>
                        </div>
                    `).join('')}
                </div>

                <!-- Hours Grid (07:00 to 18:00) -->
                <div class="relative divide-y divide-slate-100">
        `;

        for (let h = 7; h <= 17; h++) {
            const timeLabel = `${String(h).padStart(2, '0')}:00`;
            html += `
                <div class="grid grid-cols-7 min-h-[64px] border-b border-slate-100 last:border-b-0">
                    <div class="p-2 border-r border-slate-200 text-xs font-mono text-slate-400 text-right pr-3 select-none">
                        ${timeLabel}
                    </div>
                    ${dates.map((d) => {
                        const dateStr = formatDate(d);
                        // Find events starting in this hour on this date
                        const cellEvents = events.filter(e => {
                            const eDate = e.start.split('T')[0];
                            const eTime = e.start.split('T')[1];
                            const eHour = parseInt(eTime.split(':')[0], 10);
                            return eDate === dateStr && eHour === h;
                        });

                        return `
                            <div class="p-1 border-r border-slate-100 last:border-r-0 relative group hover:bg-slate-50/50 transition-colors">
                                ${cellEvents.map(e => `
                                    <div class="mb-1 p-2 rounded-lg text-white shadow-2xs cursor-pointer hover:brightness-95 transition-all text-xs"
                                         style="background-color: ${e.color};"
                                         data-event-id="${e.id}">
                                        <div class="font-bold truncate leading-tight">${e.title}</div>
                                        <div class="text-[10px] opacity-90 truncate">${e.start.split('T')[1]} - ${e.end.split('T')[1]}</div>
                                        <div class="text-[10px] opacity-80 truncate">${e.room_name}</div>
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

    function renderMonthView(range, events) {
        const days = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        const firstDayIndex = range.start.getDay();
        const totalDays = range.end.getDate();

        let html = `
        <div class="overflow-x-auto">
            <div class="min-w-[650px]">
                <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-100/70 text-center text-xs font-bold text-slate-700">
                    ${days.map(d => `<div class="py-2.5">${d}</div>`).join('')}
                </div>
                <div class="grid grid-cols-7 border-l border-t border-slate-200">
        `;

        // Empty cells before start
        for (let i = 0; i < firstDayIndex; i++) {
            html += `<div class="min-h-[100px] bg-slate-50/40 border-r border-b border-slate-200 p-2 text-slate-300"></div>`;
        }

        // Days of month
        for (let d = 1; d <= totalDays; d++) {
            const thisDate = new Date(range.start.getFullYear(), range.start.getMonth(), d);
            const dateStr = formatDate(thisDate);
            const dayEvents = events.filter(e => e.start.split('T')[0] === dateStr);
            const isToday = dateStr === formatDate(new Date());

            html += `
                <div class="min-h-[100px] border-r border-b border-slate-200 p-2 hover:bg-slate-50/50 transition-colors ${isToday ? 'bg-indigo-50/30' : ''}">
                    <div class="flex items-center justify-between mb-1.5">
                        <span class="text-xs font-bold ${isToday ? 'w-5 h-5 flex items-center justify-center bg-indigo-600 text-white rounded-full' : 'text-slate-700'}">${d}</span>
                        ${dayEvents.length > 0 ? `<span class="text-[10px] font-semibold text-slate-400">${dayEvents.length} kegiatan</span>` : ''}
                    </div>
                    <div class="space-y-1">
                        ${dayEvents.slice(0, 3).map(e => `
                            <div class="text-[10px] font-semibold truncate px-1.5 py-0.5 rounded text-white cursor-pointer hover:opacity-90"
                                 style="background-color: ${e.color}"
                                 data-event-id="${e.id}">
                                ${e.start.split('T')[1]} ${e.title}
                            </div>
                        `).join('')}
                        ${dayEvents.length > 3 ? `<div class="text-[9px] text-slate-500 font-medium">+${dayEvents.length - 3} lainnya</div>` : ''}
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
            time.textContent = `${dateStr} &bull; ${startTime} - ${endTime} WIB`;
            time.innerHTML = `${dateStr} &bull; <span class="font-mono font-bold">${startTime} - ${endTime}</span> WIB`;
        }
        if (room) room.textContent = ev.room_name;

        if (badge) {
            if (ev.type === 'lecture') {
                badge.className = 'inline-block px-2.5 py-1 text-xs font-bold rounded-lg bg-blue-100 text-blue-800';
                badge.textContent = 'Perkuliahan Reguler';
            } else {
                badge.className = 'inline-block px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-100 text-amber-800';
                badge.textContent = 'Peminjaman Acara Ormawa';
            }
        }

        if (extraLabel && extraValue) {
            if (ev.type === 'lecture') {
                extraLabel.textContent = 'Dosen & Kelas:';
                extraValue.textContent = `${ev.lecturer_name || '-'} (Kelas: ${ev.class_name || '-'})`;
            } else {
                extraLabel.textContent = 'Penyelenggara:';
                extraValue.textContent = ev.organizer || '-';
            }
        }

        detailModal.classList.remove('hidden');
    }

    // Initial load
    fetchAndRender();
});
