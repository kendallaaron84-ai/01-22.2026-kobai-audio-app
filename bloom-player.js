/**
 * KOBA-I UNIVERSAL PLAYER
 * Version 6.0 - Mobile Lock Screen & Media Session API
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // 1. INIT MAIN PLAYER
    const mainRoot = document.getElementById('koba-bloom-root');
    if (mainRoot && window.kobaData) {
        initPlayer(mainRoot, window.kobaData, 'full');
    }

    // 2. INIT MINI PLAYERS
    const miniRoots = document.querySelectorAll('.koba-mini-root');
    miniRoots.forEach(root => {
        if(root.dataset.config) {
            const config = JSON.parse(root.dataset.config);
            initPlayer(root, config, 'mini');
        }
    });

    function initPlayer(root, data, mode) {
        const chapters = data.chapters || [];
        if(chapters.length === 0) return;

        // STATE
        let currentIndex = 0;
        let isPlaying = false;
        let mediaEl = null; 
        let transcriptData = null;
        let bookmarks = [];

        // --- SMART MODULE SENSOR ---
        if (mode === 'full') {
            const resizeObserver = new ResizeObserver(entries => {
                for (let entry of entries) {
                    if (window.innerWidth > 900) {
                        if (entry.contentRect.width < 650) {
                            root.classList.add('k-mode-compact');
                        } else {
                            root.classList.remove('k-mode-compact');
                        }
                    } else {
                        root.classList.remove('k-mode-compact');
                    }
                }
            });
            resizeObserver.observe(root);
        }

        // --- RENDER HTML ---
        if (mode === 'mini') {
            root.classList.add('koba-mini-container');
            root.innerHTML = `
                <div class="k-mini-shell">
                    <div class="k-mini-cover" style="background-image:url('${data.coverUrl}')"></div>
                    <button class="k-mini-play-btn">▶</button>
                    <div class="k-mini-info">
                        <div class="k-mini-title">${data.title}</div>
                        <div class="k-mini-scrubber"><div class="k-mini-progress"></div></div>
                    </div>
                </div>`;
        } else {
            root.innerHTML = `
                <div class="k-bloom-bg" style="background-image: url('${data.bgImage}')"></div>
                <img src="${data.logoUrl}" class="k-bloom-logo" alt="KOBA-I">
                <div class="k-bloom-interface">
                    <div class="k-bloom-stage">
                        <div id="k-media-container" class="k-media-box"></div>
                        
                        <div id="k-read-scrollbox" class="k-read-scrollbox">
                            <div style="opacity:0.5; margin-top:100px;">Loading Transcript...</div>
                        </div>

                        <div class="k-bloom-controls">
                            <div class="k-scrubber" id="k-scrubber"><div class="k-progress" id="k-progress"></div></div>
                            <div class="k-time-row"><span id="k-curr-time">0:00</span><span id="k-dur-time">0:00</span></div>
                            <div class="k-buttons">
                                <button class="k-btn-icon" id="k-speed-btn" title="Speed">1x</button>
                                <button class="k-btn-icon" id="k-rw-btn" title="Rewind 30s">↺ 30</button>
                                <button class="k-btn-icon" id="k-prev-btn" title="Previous Chapter">⏮</button>
                                <button class="k-btn-main" id="k-play-btn">▶</button>
                                <button class="k-btn-icon" id="k-next-btn" title="Next Chapter">⏭</button>
                                <button class="k-btn-icon" id="k-ff-btn" title="Forward 30s">30 ↻</button>
                                <div class="k-actions">
                                    <button class="k-btn-icon" id="k-mark-btn" title="Bookmark">🔖</button>
                                    <button class="k-btn-icon" id="k-text-btn" title="Read Along" style="opacity:0.3; cursor:default;">📝</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="k-bloom-sidebar">
                        <div class="k-tabs"><button class="k-tab active" id="tab-chapters">Chapters</button><button class="k-tab" id="tab-bookmarks">Bookmarks</button></div>
                        <div class="k-list" id="k-list-container"></div>
                    </div>
                </div>
            `;
        }

        // REFERENCES
        const playBtn = root.querySelector(mode === 'mini' ? '.k-mini-play-btn' : '#k-play-btn');
        const progressBar = root.querySelector(mode === 'mini' ? '.k-mini-progress' : '#k-progress');
        const scrubber = root.querySelector(mode === 'mini' ? '.k-mini-scrubber' : '#k-scrubber');
        const mediaBox = root.querySelector('#k-media-container');
        const listContainer = root.querySelector('#k-list-container');
        const currTimeEl = root.querySelector('#k-curr-time');
        const durTimeEl = root.querySelector('#k-dur-time');
        
        // Teleprompter References
        const textBtn = root.querySelector('#k-text-btn');
        const readBox = root.querySelector('#k-read-scrollbox');
        
        function loadChapter(index) {
            if (index < 0 || index >= chapters.length) return;
            currentIndex = index;
            const chap = chapters[index];

            // Reset View
            if(mode === 'full') {
                root.classList.remove('k-mode-reading'); 
                if(readBox) readBox.innerHTML = '<div style="opacity:0.5; padding-top:120px;">Loading Transcript...</div>';
            }

            if(mediaBox) mediaBox.innerHTML = '';
            if (mediaEl) { 
                mediaEl.pause(); 
                mediaEl.removeAttribute('src'); // Clean cleanup
                mediaEl = null; 
            }

            if (mode === 'full') {
                if (chap.type === 'video') {
                    mediaEl = document.createElement('video');
                    mediaEl.className = 'k-video-element';
                    // Video needs to play inline on mobile
                    mediaEl.setAttribute('playsinline', 'true');
                } else {
                    const cover = document.createElement('div');
                    cover.className = 'k-bloom-cover';
                    cover.style.backgroundImage = `url('${data.coverUrl}')`;
                    mediaBox.appendChild(cover);
                    mediaEl = document.createElement('audio');
                }
                mediaBox.appendChild(mediaEl);
            } else {
                mediaEl = new Audio(); 
            }
            
            mediaEl.src = chap.url;
            mediaEl.preload = 'metadata'; // Load metadata early for lock screen

            // --- LOCK SCREEN / MEDIA SESSION API ---
            if ('mediaSession' in navigator) {
                navigator.mediaSession.metadata = new MediaMetadata({
                    title: chap.title,
                    artist: data.title, // Use Book Title as "Artist"
                    album: "KOBA-I Audio",
                    artwork: [
                        { src: data.coverUrl, sizes: '512x512', type: 'image/jpeg' }
                    ]
                });

                // Link Phone Controls to Player Functions
                navigator.mediaSession.setActionHandler('play', () => { togglePlay(); });
                navigator.mediaSession.setActionHandler('pause', () => { togglePlay(); });
                navigator.mediaSession.setActionHandler('previoustrack', () => { loadChapter(currentIndex - 1); setTimeout(togglePlay, 500); });
                navigator.mediaSession.setActionHandler('nexttrack', () => { loadChapter(currentIndex + 1); setTimeout(togglePlay, 500); });
                navigator.mediaSession.setActionHandler('seekto', (details) => {
                    if (mediaEl && details.seekTime) mediaEl.currentTime = details.seekTime;
                });
            }
            // ---------------------------------------

            mediaEl.addEventListener('timeupdate', updateProgress);
            mediaEl.addEventListener('ended', () => { if(mode === 'full') loadChapter(currentIndex + 1); });
            mediaEl.addEventListener('loadedmetadata', () => { if(durTimeEl) durTimeEl.innerText = formatTime(mediaEl.duration); });

            if(playBtn) playBtn.innerText = '▶';
            isPlaying = false;
            
            if(mode === 'full') { renderList(); loadTranscript(chap); }
        }

        function togglePlay() {
            if (!mediaEl) return;
            if (mediaEl.paused) { 
                mediaEl.play()
                    .then(() => {
                        playBtn.innerText = '❚❚'; 
                        isPlaying = true;
                        if('mediaSession' in navigator) navigator.mediaSession.playbackState = "playing";
                    })
                    .catch(e => console.log("Play interrupted:", e));
            } else { 
                mediaEl.pause(); 
                playBtn.innerText = '▶'; 
                isPlaying = false;
                if('mediaSession' in navigator) navigator.mediaSession.playbackState = "paused";
            }
        }

        function updateProgress() {
            if (!mediaEl) return;
            const pct = (mediaEl.currentTime / mediaEl.duration) * 100;
            if(progressBar) progressBar.style.width = `${pct}%`;
            if(currTimeEl) currTimeEl.innerText = formatTime(mediaEl.currentTime);
            
            // Sync Text
            if (transcriptData && root.classList.contains('k-mode-reading')) syncText(mediaEl.currentTime);
        }

        function formatTime(s) {
            if (!s || isNaN(s)) return "0:00";
            const m = Math.floor(s / 60);
            const sec = Math.floor(s % 60);
            return `${m}:${sec < 10 ? '0' : ''}${sec}`;
        }

        function renderList() {
            if(!listContainer) return;
            listContainer.innerHTML = '';
            chapters.forEach((c, i) => {
                const row = document.createElement('div');
                row.className = `k-list-item ${i === currentIndex ? 'active' : ''}`;
                row.innerHTML = `<span style="opacity:0.5; width:20px;">${i+1}</span><div class="k-item-info"><span class="k-item-title">${c.title}</span></div>`;
                row.onclick = () => { loadChapter(i); setTimeout(togglePlay, 500); };
                listContainer.appendChild(row);
            });
        }

        function loadTranscript(chap) {
            if(!textBtn) return;
            transcriptData = null;
            textBtn.style.opacity = '0.3';
            textBtn.style.cursor = 'default';
            
            // Safe URL check for transcript
            if (chap.transcript_file_url && chap.transcript_file_url.includes('.json')) {
                fetch(chap.transcript_file_url)
                    .then(r => r.json())
                    .then(json => {
                        transcriptData = [];
                        if(json.results) {
                            json.results.forEach(res => {
                                if(res.alternatives) res.alternatives[0].words.forEach(w => {
                                    transcriptData.push({ word: w.word, start: parseFloat(w.startOffset.replace('s','')), end: parseFloat(w.endOffset.replace('s','')) });
                                });
                            });
                        }
                        if(transcriptData.length > 0) {
                            textBtn.style.opacity = '1';
                            textBtn.style.cursor = 'pointer';
                            if(readBox) {
                                readBox.innerHTML = '';
                                transcriptData.forEach(t => {
                                    const span = document.createElement('span');
                                    span.className = 'k-word'; span.innerText = t.word + ' ';
                                    span.dataset.start = t.start; span.dataset.end = t.end;
                                    span.onclick = () => { if(mediaEl) { mediaEl.currentTime = t.start; mediaEl.play(); isPlaying = true; playBtn.innerText = '❚❚'; }};
                                    readBox.appendChild(span);
                                });
                            }
                        }
                    })
                    .catch(err => {
                        console.log('Transcript load failed', err);
                        if(readBox) readBox.innerHTML = '<div style="opacity:0.5; padding-top:120px;">Transcript Not Available</div>';
                    });
            } else {
                 if(readBox) readBox.innerHTML = '<div style="opacity:0.5; padding-top:120px;">Transcript Not Available</div>';
            }
        }

        function syncText(time) {
            if(!readBox) return;
            const words = readBox.querySelectorAll('.k-word');
            let activeWord = null;
            words.forEach(w => {
                const start = parseFloat(w.dataset.start);
                const end = parseFloat(w.dataset.end);
                if (time >= start && time <= end) {
                    w.classList.add('active');
                    activeWord = w;
                } else {
                    w.classList.remove('active');
                }
            });
            if(activeWord) {
                activeWord.scrollIntoView({behavior: "smooth", block: "center", inline: "nearest"});
            }
        }

        if(playBtn) playBtn.onclick = togglePlay;
        if(scrubber) scrubber.onclick = (e) => {
            if(!mediaEl) return;
            const rect = scrubber.getBoundingClientRect();
            mediaEl.currentTime = ((e.clientX - rect.left) / rect.width) * mediaEl.duration;
        };

        if (mode === '