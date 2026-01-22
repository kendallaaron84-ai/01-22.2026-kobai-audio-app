/**
 * KOBA-I UNIVERSAL PLAYER
 * Version 5.0 - Teleprompter Mode
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

        // REFERENCES & LOGIC
        const playBtn = root.querySelector(mode === 'mini' ? '.k-mini-play-btn' : '#k-play-btn');
        const progressBar = root.querySelector(mode === 'mini' ? '.k-mini-progress' : '#k-progress');
        const scrubber = root.querySelector(mode === 'mini' ? '.k-mini-scrubber' : '#k-scrubber');
        const mediaBox = root.querySelector('#k-media-container');
        const listContainer = root.querySelector('#k-list-container');
        const currTimeEl = root.querySelector('#k-curr-time');
        const durTimeEl = root.querySelector('#k-dur-time');
        
        // New Teleprompter References
        const textBtn = root.querySelector('#k-text-btn');
        const readBox = root.querySelector('#k-read-scrollbox');
        
        function loadChapter(index) {
            if (index < 0 || index >= chapters.length) return;
            currentIndex = index;
            const chap = chapters[index];

            // Reset View (Show Cover, Hide Text)
            if(mode === 'full') {
                root.classList.remove('k-mode-reading'); 
                if(readBox) readBox.innerHTML = '<div style="opacity:0.5; padding-top:120px;">Loading Transcript...</div>';
            }

            if(mediaBox) mediaBox.innerHTML = '';
            if (mediaEl) { mediaEl.pause(); mediaEl.src = ""; mediaEl = null; }

            if (mode === 'full') {
                if (chap.type === 'video') {
                    mediaEl = document.createElement('video');
                    mediaEl.className = 'k-video-element';
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
            mediaEl.addEventListener('timeupdate', updateProgress);
            mediaEl.addEventListener('ended', () => { if(mode === 'full') loadChapter(currentIndex + 1); });
            mediaEl.addEventListener('loadedmetadata', () => { if(durTimeEl) durTimeEl.innerText = formatTime(mediaEl.duration); });

            if(playBtn) playBtn.innerText = '▶';
            isPlaying = false;
            
            if(mode === 'full') { renderList(); loadTranscript(chap); }
        }

        function togglePlay() {
            if (!mediaEl) return;
            if (mediaEl.paused) { mediaEl.play(); playBtn.innerText = '❚❚'; isPlaying = true; } 
            else { mediaEl.pause(); playBtn.innerText = '▶'; isPlaying = false; }
        }

        function updateProgress() {
            if (!mediaEl) return;
            const pct = (mediaEl.currentTime / mediaEl.duration) * 100;
            if(progressBar) progressBar.style.width = `${pct}%`;
            if(currTimeEl) currTimeEl.innerText = formatTime(mediaEl.currentTime);
            
            // Sync Text in Teleprompter
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
            
            if (chap.transcript_file_url) {
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
                            
                            // Build HTML for scrollbox
                            if(readBox) {
                                readBox.innerHTML = '';
                                transcriptData.forEach(t => {
                                    const span = document.createElement('span');
                                    span.className = 'k-word'; span.innerText = t.word + ' ';
                                    span.dataset.start = t.start; span.dataset.end = t.end;
                                    // Clicking a word jumps to that time
                                    span.onclick = () => { if(mediaEl) { mediaEl.currentTime = t.start; mediaEl.play(); isPlaying = true; playBtn.innerText = '❚❚'; }};
                                    readBox.appendChild(span);
                                });
                            }
                        }
                    });
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

            // Auto-Scroll Logic
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

        if (mode === 'full') {
            const nextBtn = root.querySelector('#k-next-btn');
            const prevBtn = root.querySelector('#k-prev-btn');
            const ffBtn = root.querySelector('#k-ff-btn');
            const rwBtn = root.querySelector('#k-rw-btn');
            const speedBtn = root.querySelector('#k-speed-btn');
            const markBtn = root.querySelector('#k-mark-btn');
            const tabChapters = root.querySelector('#tab-chapters');
            const tabBookmarks = root.querySelector('#tab-bookmarks');
            
            if(nextBtn) nextBtn.onclick = () => { loadChapter(currentIndex + 1); setTimeout(togglePlay, 500); };
            if(prevBtn) prevBtn.onclick = () => { loadChapter(currentIndex - 1); setTimeout(togglePlay, 500); };
            if(ffBtn) ffBtn.onclick = () => { if(mediaEl) mediaEl.currentTime += 30; };
            if(rwBtn) rwBtn.onclick = () => { if(mediaEl) mediaEl.currentTime -= 30; };
            if(speedBtn) speedBtn.onclick = () => { 
                if(!mediaEl) return;
                let r = mediaEl.playbackRate;
                r = (r === 1) ? 1.5 : (r === 1.5 ? 2 : 1);
                mediaEl.playbackRate = r;
                speedBtn.innerText = r + 'x';
            };
            
            // --- NEW: Toggle Reading Mode ---
            if(textBtn) textBtn.onclick = () => { 
                if(transcriptData) {
                    root.classList.toggle('k-mode-reading');
                    // If we just turned it on, sync immediately
                    if(root.classList.contains('k-mode-reading') && mediaEl) syncText(mediaEl.currentTime);
                }
            };
            // ---------------------------------

            if(markBtn) markBtn.onclick = () => {
                if(!mediaEl) return;
                bookmarks.push({ index: currentIndex, time: mediaEl.currentTime, name: `${chapters[currentIndex].title} @ ${formatTime(mediaEl.currentTime)}` });
                alert("Bookmark Added!");
            };
            if(tabBookmarks) tabBookmarks.onclick = () => {
                tabChapters.classList.remove('active'); tabBookmarks.classList.add('active');
                listContainer.innerHTML = '';
                if(bookmarks.length === 0) listContainer.innerHTML = '<div style="padding:20px; color:#fff; text-align:center; font-size:12px; opacity:0.5;">No bookmarks yet</div>';
                else bookmarks.forEach(b => {
                    const row = document.createElement('div'); row.className = 'k-list-item';
                    row.innerHTML = `<span>🔖</span><span class="k-item-title" style="margin-left:10px">${b.name}</span>`;
                    row.onclick = () => { loadChapter(b.index); setTimeout(() => { mediaEl.currentTime = b.time; togglePlay(); }, 500); };
                    listContainer.appendChild(row);
                });
            };
            if(tabChapters) tabChapters.onclick = () => { tabBookmarks.classList.remove('active'); tabChapters.classList.add('active'); renderList(); };
        }
        loadChapter(0);
    }
});