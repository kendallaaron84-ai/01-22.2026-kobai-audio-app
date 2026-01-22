<?php
/**
 * KOBA-I Audio: Production Studio
 * * Status: Stable / Final Label Polish
 */
if ( ! defined( 'ABSPATH' ) ) exit;

if (class_exists('Koba_Safety_Sentinel') && !Koba_Safety_Sentinel::scan()) return;

function koba_render_production_suite() {
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;

    // 1. DASHBOARD
    if (!$post_id) {
        $books = get_posts(['post_type' => 'koba_publication', 'post_status' => 'any', 'numberposts' => -1]);
        ?>
        <div class="koba-studio-wrap">
            <header class="studio-header">
                <div class="brand-group">
                    <img src="<?php echo KOBA_IA_URL . 'assets/koba-logo-text.png'; ?>" alt="KOBA-I">
                    <div class="brand-text">
                        <h1>Production Studio</h1>
                        <span class="version-badge">v3.5 Sentinel Active</span>
                    </div>
                </div>
                <a href="<?php echo admin_url('post-new.php?post_type=koba_publication'); ?>" class="btn-orange">+ NEW PUBLICATION</a>
            </header>
            <div class="book-grid">
                <?php foreach($books as $book): 
                    $cover = get_post_meta($book->ID, '_koba_cover_art_url', true);
                    $status = get_post_status($book->ID);
                    $status_label = ($status === 'publish') ? 'LIVE' : 'DRAFT';
                    $status_color = ($status === 'publish') ? '#238636' : '#94a3b8';
                ?>
                    <a href="<?php echo admin_url("edit.php?post_type=koba_publication&page=koba-studio&post=" . $book->ID); ?>" class="book-card">
                        <div class="book-cover-wrap">
                            <div class="book-cover" style="background-image:url('<?php echo esc_url($cover ?: 'https://placehold.co/400x400/1e293b/475569?text=NO+ART'); ?>');"></div>
                            <span class="status-pill" style="background:<?php echo $status_color; ?>"><?php echo $status_label; ?></span>
                        </div>
                        <div class="book-info"><strong><?php echo get_the_title($book->ID); ?></strong></div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        koba_render_studio_styles();
        return;
    }

    // 2. EDITOR
    wp_enqueue_media();
    
    $title    = get_the_title($post_id);
    $status   = get_post_status($post_id);
    $author   = get_post_meta($post_id, '_koba_author_name', true);
    $cover = get_post_meta($post_id, '_koba_cover_art_url', true);
    if ($cover) {
        $cover = set_url_scheme($cover, 'https'); 
    }
    $bg_image = get_post_meta($post_id, '_koba_bg_image_url', true);
    if ($bg_image) {
        $bg_image = set_url_scheme($bg_image, 'https');
    }
    $chapters = json_decode(get_post_meta($post_id, '_koba_chapters_data', true), true) ?: [];

    koba_render_studio_styles();
    ?>

    <div class="koba-studio-wrap">
        <div class="studio-header">
            <div class="brand-group">
                <img src="<?php echo KOBA_IA_URL . 'assets/koba-logo-text.png'; ?>" alt="KOBA-I">
                <div class="brand-text">
                    <h2>Production Studio</h2>
                    <span class="version-badge">v3.5 Sentinel Active</span>
                </div>
            </div>
            
            <div class="actions">
                <div class="shortcode-box" onclick="copyShortcode(this)" title="Click to Copy">[koba_player id="<?php echo $post_id; ?>"]</div>
                
                <select id="k-status" class="koba-select" style="width:auto; margin:0; border-color:#f97316;">
                    <option value="publish" <?php selected($status, 'publish'); ?>>● LIVE (Published)</option>
                    <option value="draft" <?php selected($status, 'draft'); ?>>○ DRAFT (Hidden)</option>
                </select>

                <a href="<?php echo get_permalink($post_id); ?>" target="_blank" class="btn-ghost">PREVIEW ↗</a>
                <button type="button" id="k-save" class="btn-orange">SAVE & PUBLISH</button>
            </div>
        </div>

        <div class="glass-card">
            <div class="grid-2">
                <div class="field-col">
                    <label class="koba-label">Publication Title</label>
                    <input type="text" id="k-title" class="koba-input large-text" value="<?php echo esc_attr($title); ?>">
                    <label class="koba-label mt-20">Author / Performer</label>
                    <input type="text" id="k-author" class="koba-input" value="<?php echo esc_attr($author); ?>">
                </div>
                <div class="field-col">
                    <label class="koba-label">Cover Art</label>
                    <div class="media-row">
                        <input type="text" id="k-cover" class="koba-input" value="<?php echo esc_attr($cover); ?>" readonly>
                        <button type="button" class="btn-select media-trigger" data-target="#k-cover">SELECT</button>
                    </div>
                    <label class="koba-label mt-10">Player Background</label>
                    <div class="media-row">
                        <input type="text" id="k-bg" class="koba-input" value="<?php echo esc_attr($bg_image); ?>" readonly>
                        <button type="button" class="btn-select media-trigger" data-target="#k-bg">SELECT</button>
                    </div>
                </div>
            </div>
        </div>

        <h3 class="koba-section-title">Production Timeline</h3>
        <div id="k-list" class="chapter-list">
            <?php foreach($chapters as $index => $c): 
                $ai_status = $c['ai_status'] ?? 'idle'; 
                $type = $c['type'] ?? 'audio';
                $ghost_id = $c['attachment_id'] ?? 0;
                $has_file = !empty($c['transcript_file_url']);
                
                // COST MATH
                $cost_display = "Est: $0.00";
                if ($ghost_id) {
                    $meta = wp_get_attachment_metadata($ghost_id);
                    $raw = $meta['length'] ?? ($meta['length_formatted'] ?? 0);
                    $sec = 0;
                    if(is_numeric($raw)) $sec = intval($raw);
                    elseif(is_string($raw) && strpos($raw, ':')!==false) {
                        $p = explode(':', $raw);
                        $sec = (count($p)===2) ? ($p[0]*60)+$p[1] : ($p[0]*3600)+($p[1]*60)+$p[2];
                    }
                    if($sec > 0) $cost_display = "Est: $" . number_format(ceil($sec/60)*0.02, 2);
                }
                
                // --- STATUS UPDATES ---
                // 1. Text Field Status
                $json_status = $has_file ? "✓ TRANSCRIPT ACTIVE" : "Waiting for AI...";
                $status_color = $has_file ? "#238636" : "#8b949e";
                
                // 2. Button Status
                $btn_text = 'GENERATE TRANSCRIPT';
                if ($ai_status === 'processing') $btn_text = 'PROCESSING...';
                if ($ai_status === 'completed')  $btn_text = '✓ COMPLETE';
            ?>
                <div class="koba-chapter-row" data-index="<?php echo $index; ?>" data-id="<?php echo $c['id']; ?>">
                    <span class="drag-handle">⠿</span>
                    <select class="k-chap-type koba-select" style="width:90px;">
                        <option value="audio" <?php selected($type, 'audio'); ?>>Audio</option>
                        <option value="video" <?php selected($type, 'video'); ?>>Video</option>
                    </select>
                    <div style="flex:1;">
                        <input type="text" class="k-chap-title koba-input" value="<?php echo esc_attr($c['title']); ?>">
                        <div class="koba-row-controls">
                            <input type="text" readonly class="koba-input-sm" value="<?php echo $json_status; ?>" style="color:<?php echo $status_color; ?>; border-color:<?php echo $status_color; ?>;">
                            
                            <span class="cost-badge"><?php echo $cost_display; ?></span>
                            
                            <button type="button" class="btn-ai <?php echo $ai_status; ?>" onclick="triggerAI(this, <?php echo $index; ?>)">
                                <?php echo $btn_text; ?>
                            </button>
                        </div>
                    </div>
                    <input type="hidden" class="k-chap-att-id" value="<?php echo esc_attr($ghost_id); ?>">
                    <button type="button" class="btn-remove">✕</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="k-add" class="btn-dashed">+ ADD MEDIA</button>
    </div>

    <script>
    function copyShortcode(el) { navigator.clipboard.writeText(el.innerText); var t=el.innerText; el.innerText="COPIED!"; setTimeout(()=>el.innerText=t, 1000); }

    jQuery(document).ready(function($){
        var file_frame; 
        $(document).on('click', '.media-trigger', function(e){
            e.preventDefault();
            var $btn = $(this);
            var target = $btn.data('target');
            if (file_frame) { 
                file_frame.open(); 
                file_frame.off('select');
                file_frame.on('select', function(){
                    var att = file_frame.state().get('selection').first().toJSON();
                    $(target).val(att.url);
                });
                return;
            }
            file_frame = wp.media({ title: 'Select Media', button: {text: 'Use Media'}, multiple: false });
            file_frame.on('select', function(){
                var att = file_frame.state().get('selection').first().toJSON();
                $(target).val(att.url);
            });
            file_frame.open();
        });

        $('#k-add').click(function(e){
            var frame = wp.media({ title: 'Select Secure Media', multiple: true, library: {type: 'audio,video'} }).on('select', function(){
                frame.state().get('selection').each(function(att){
                    var m = att.toJSON();
                    var type = m.type === 'video' ? 'video' : 'audio';
                    var sec = m.length || 0; 
                    if(!sec && m.fileLength && m.fileLength.includes(':')) {
                        var p = m.fileLength.split(':'); sec = (+p[0])*60 + (+p[1]);
                    }
                    var cost = (sec>0) ? "Est: $" + (Math.ceil(sec/60)*0.02).toFixed(2) : "Est: Calc...";

                    var row = `<div class="koba-chapter-row" data-id="${Date.now()}" data-index="new">
                        <span class="drag-handle">⠿</span>
                        <select class="k-chap-type koba-select" style="width:90px;">
                            <option value="audio" ${type==='audio'?'selected':''}>Audio</option>
                            <option value="video" ${type==='video'?'selected':''}>Video</option>
                        </select>
                        <div style="flex:1;">
                            <input type="text" class="k-chap-title koba-input" value="${m.title}">
                            <div class="koba-row-controls">
                                <input type="text" readonly class="koba-input-sm" value="Waiting for Save..." style="color:#8b949e;">
                                <span class="cost-badge">${cost}</span>
                                <button type="button" class="btn-ai" disabled>SAVE TO ACTIVATE</button>
                            </div>
                        </div>
                        <input type="hidden" class="k-chap-att-id" value="${m.id}">
                        <button class="btn-remove">✕</button>
                    </div>`;
                    $('#k-list').append(row);
                });
            }).open();
        });

        $('#k-save').click(function(){
            var btn = $(this); btn.text('SAVING...').prop('disabled', true);
            var chapters = [];
            $('.koba-chapter-row').each(function(){
                chapters.push({
                    id: $(this).data('id'),
                    title: $(this).find('.k-chap-title').val(),
                    type: $(this).find('.k-chap-type').val(),
                    attachment_id: $(this).find('.k-chap-att-id').val(),
                    ai_status: $(this).find('.btn-ai').hasClass('completed') ? 'completed' : ($(this).find('.btn-ai').hasClass('processing') ? 'processing' : 'idle')
                });
            });

            $.post(ajaxurl, { 
                action: 'koba_save_studio_data', 
                post_id: <?php echo $post_id; ?>, 
                title: $('#k-title').val(), 
                status: $('#k-status').val(),
                author: $('#k-author').val(), 
                cover: $('#k-cover').val(), 
                bg_image: $('#k-bg').val(), 
                chapters: JSON.stringify(chapters), 
                nonce: '<?php echo wp_create_nonce("k_studio_nonce"); ?>' 
            }, function(){ 
                btn.text('SAVED!').addClass('success'); 
                setTimeout(()=>window.location.reload(), 500);
            });
        });

        $(document).on('click', '.btn-remove', function(){ $(this).closest('.koba-chapter-row').remove(); });
        if($.fn.sortable) { $("#k-list").sortable(); }
    });

    window.triggerAI = function(btn, index) {
        var $btn = jQuery(btn);
        if ($btn.hasClass('processing') || $btn.is(':disabled')) return;
        
        // If completed, allow refresh but ask confirmation
        if ($btn.hasClass('completed')) {
            if(!confirm("Re-generate Transcript?\nThis will charge the cost again.")) return;
        } else {
            var cost = $btn.siblings('.cost-badge').text();
            if(!confirm("Start AI?\n"+cost)) return;
        }

        $btn.text('UPLOADING...').addClass('processing').removeClass('completed');
        jQuery.post(ajaxurl, {
            action: 'koba_transcribe_chapter',
            post_id: <?php echo $post_id; ?>,
            chapter_index: index,
            nonce: '<?php echo wp_create_nonce("k_studio_nonce"); ?>'
        }, function(res){
            if(!res.success) { alert(res.data); $btn.removeClass('processing').text('GENERATE'); }
            else { $btn.text('PROCESSING...'); }
        });
    };
    </script>
    <?php
}

function koba_render_studio_styles() {
    echo '<style>
        :root { --koba-bg: #0d1117; --koba-panel: #161b22; --koba-border: #30363d; --koba-text: #c9d1d9; --koba-accent: #f97316; }
        .koba-studio-wrap { background: var(--koba-bg); color: var(--koba-text); min-height: calc(100vh - 32px); margin: -10px -20px 0 -22px; padding: 30px; box-sizing: border-box; font-family: -apple-system, system-ui, sans-serif; }
        .studio-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid var(--koba-border); padding-bottom: 20px; }
        .brand-group { display: flex; align-items: center; gap: 15px; }
        .brand-group img { height: 40px; display:block; }
        .brand-text h1, .brand-text h2 { margin: 0; line-height: 1.2; color: #fff; font-size: 18px; }
        .actions { display: flex; gap: 10px; align-items: center; }
        .glass-card { background: var(--koba-panel); border: 1px solid var(--koba-border); border-radius: 8px; padding: 25px; margin-bottom: 20px; }
        .koba-input, .koba-select { background: #010409; border: 1px solid var(--koba-border); color: #fff; padding: 10px; border-radius: 6px; width: 100%; display:block; margin-bottom: 10px; }
        .koba-input-sm { background: #010409; border: 1px solid var(--koba-border); color: #fff; padding: 5px 10px; border-radius: 4px; font-size: 11px; width: 100%; }
        .btn-orange { background: var(--koba-accent); color: #0d1117; border:none; padding: 0 20px; height: 40px; font-weight: 800; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-select { background: #334155; color: #fff; border: 1px solid var(--koba-border); padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn-ghost { border: 1px solid var(--koba-border); color: var(--koba-text); padding: 0 15px; height: 40px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; }
        .book-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 20px; }
        .book-card { background: var(--koba-panel); border: 1px solid var(--koba-border); border-radius: 8px; overflow: hidden; text-decoration: none; color: white; display: block; transition: transform 0.2s; }
        .book-card:hover { transform: translateY(-5px); border-color: var(--koba-accent); }
        .book-cover-wrap { position: relative; width: 100%; padding-top: 100%; background: #000; }
        .book-cover { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background-size: cover; background-position: center; }
        .status-pill { position: absolute; top: 8px; right: 8px; font-size: 10px; font-weight: bold; color: white; padding: 3px 6px; border-radius: 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.5); }
        .book-info { padding: 12px; font-size: 14px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .koba-chapter-row { display: flex; align-items: flex-start; gap: 15px; background: var(--koba-panel); padding: 15px; border: 1px solid var(--koba-border); margin-bottom: 8px; border-radius: 6px; }
        .koba-row-controls { display:flex; align-items:center; gap:10px; }
        .drag-handle { cursor: move; color: #8b949e; margin-top: 15px; }
        .grid-2 { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
        .media-row { display: flex; gap: 10px; }
        .btn-dashed { width: 100%; border: 2px dashed var(--koba-border); background: transparent; color: #8b949e; padding: 20px; border-radius: 8px; cursor: pointer; font-weight: 600; margin-top: 20px; }
        .version-badge { font-size: 10px; text-transform: uppercase; background: var(--koba-border); padding: 2px 6px; border-radius: 4px; color: #8b949e; display: inline-block; }
        .btn-ai { font-size: 10px; font-weight: bold; background: transparent; border: 1px solid var(--koba-accent); color: var(--koba-accent); padding: 6px 10px; border-radius: 4px; cursor: pointer; width: 140px; text-align: center; }
        .btn-ai.processing { border-color: #eab308; color: #eab308; pointer-events: none; }
        .btn-ai.completed { border-color: #238636; color: #238636; }
        .btn-remove { background:none; border:none; color:#ef4444; cursor:pointer; font-weight:bold; margin-top:15px; }
        .cost-badge { font-size: 10px; color: #8b949e; font-family: monospace; white-space:nowrap; }
        .shortcode-box { background:#0f172a; padding:0 15px; border:1px dashed #30363d; border-radius:4px; font-family:monospace; color:#f97316; font-size:11px; cursor:pointer; height:40px; display:flex; align-items:center; }
    </style>';
}