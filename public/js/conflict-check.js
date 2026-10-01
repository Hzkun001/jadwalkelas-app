/**
 * Real-time Conflict & Availability Checker
 * Automatically checks room occupancy before form submission
 */

document.addEventListener('DOMContentLoaded', function () {
    const roomEl = document.getElementById('booking_room_id');
    const dateEl = document.getElementById('booking_date');
    const startEl = document.getElementById('booking_start_time');
    const endEl = document.getElementById('booking_end_time');
    const statusContainer = document.getElementById('availability-status-container');
    const submitBtn = document.getElementById('booking-submit-btn');

    if (!roomEl || !dateEl || !startEl || !endEl || !statusContainer) return;

    let debounceTimer = null;

    function triggerCheck() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(checkAvailability, 250);
    }

    [roomEl, dateEl, startEl, endEl].forEach(el => {
        el.addEventListener('change', triggerCheck);
        el.addEventListener('input', triggerCheck);
    });

    async function checkAvailability() {
        const roomId = roomEl.value;
        const date = dateEl.value;
        const start = startEl.value;
        const end = endEl.value;

        if (!roomId || !date || !start || !end) {
            statusContainer.innerHTML = '';
            statusContainer.classList.add('hidden');
            if (submitBtn) submitBtn.disabled = false;
            return;
        }

        // Validate that start < end
        if (start >= end) {
            statusContainer.classList.remove('hidden');
            statusContainer.innerHTML = `
                <div class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl flex items-center gap-2">
                    <svg class="w-4 h-4 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <span>Jam selesai harus lebih akhir dari jam mulai.</span>
                </div>
            `;
            if (submitBtn) submitBtn.disabled = true;
            return;
        }

        statusContainer.classList.remove('hidden');
        statusContainer.innerHTML = `
            <div class="p-3 bg-slate-50 border border-slate-200 text-slate-500 text-xs rounded-xl flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-slate-400 animate-ping"></span>
                <span>Memeriksa ketersediaan ruangan & jadwal kuliah...</span>
            </div>
        `;

        try {
            const res = await fetch(`/api/check-availability?room_id=${roomId}&date=${date}&start=${start}&end=${end}`);
            const data = await res.json();

            if (data.available) {
                statusContainer.innerHTML = `
                    <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs rounded-xl flex items-center gap-2.5">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <div>
                            <span class="font-bold">Ruangan Tersedia!</span>
                            <span class="block text-emerald-700 text-[11px]">Tidak ada bentrok dengan perkuliahan maupun kegiatan lainnya.</span>
                        </div>
                    </div>
                `;
                if (submitBtn) submitBtn.disabled = false;
            } else {
                statusContainer.innerHTML = `
                    <div class="p-3 bg-rose-50 border border-rose-200 text-rose-800 text-xs rounded-xl space-y-1">
                        <div class="flex items-center gap-2 font-bold text-rose-700">
                            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                            </svg>
                            <span>Jadwal Ruangan Bentrok</span>
                        </div>
                        <p class="text-rose-600 text-[11px] leading-relaxed pl-6">
                            ${data.reason || 'Ruangan tidak dapat dipinjam pada waktu tersebut.'}
                        </p>
                    </div>
                `;
                if (submitBtn) submitBtn.disabled = true;
            }
        } catch (err) {
            console.error(err);
        }
    }

    // Run once on load if values are present (e.g. pre-selected room)
    if (roomEl.value && dateEl.value && startEl.value && endEl.value) {
        checkAvailability();
    }
});
