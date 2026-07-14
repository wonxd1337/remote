<?php
$db_file = 'db.json';
$last_sync_file = 'last_sync.txt';
$db = file_exists($db_file) ? json_decode(file_get_contents($db_file), true) : ["installed"=>[], "status"=>[], "commands"=>[]];

$installed_packages = $db['installed'] ?? [];
$status_data = $db['status'] ?? [];
$commands = $db['commands'] ?? [];
$changed = false;

foreach ($installed_packages as $pkg) {
    if (!isset($commands[$pkg])) {
        $commands[$pkg] = [
            'cmd' => 'IDLE',
            'mode' => 'public',
            'target' => '',
            'content' => '',
            'executed' => false
        ];
        $changed = true;
    }
}
foreach (array_keys($commands) as $pkg) {
    if (!in_array($pkg, $installed_packages) && $pkg !== '_file_manager') {
        unset($commands[$pkg]);
        $changed = true;
    }
}
if ($changed) {
    $db['commands'] = $commands;
    file_put_contents($db_file, json_encode($db));
}

$active_commands = $db['commands'] ?? [];
$last_sync = file_exists($last_sync_file) ? (int)file_get_contents($last_sync_file) : 0;
$time_diff = time() - $last_sync;
$is_connected = $time_diff <= 60 && !empty($installed_packages);
$auto_rejoin = $db['auto_rejoin'] ?? false;

// Hitung jumlah akun (di luar _file_manager) untuk ditampilkan di status
$account_count = 0;
foreach ($active_commands as $pkg => $c) {
    if ($pkg !== '_file_manager') $account_count++;
}

// Render potongan status koneksi -- dipakai untuk render awal DAN dijadikan acuan versi JS (pollDashboard)
function render_status_indicator($is_connected, $account_count, $time_diff, $last_sync) {
    ob_start();
    if ($is_connected) { ?>
        <span class="status-pulse online"></span>
        <div class="status-text-group">
            <span class="status-title">Terhubung — <?= $account_count ?> akun Roblox</span>
            <span class="status-sub" id="statusSub">Sync <?= $time_diff ?>s lalu</span>
        </div>
    <?php } else { ?>
        <span class="status-pulse offline"></span>
        <div class="status-text-group">
            <span class="status-title">Termux Tidak Terhubung</span>
            <span class="status-sub" id="statusSub">
                <?php if ($time_diff > 60 && $time_diff < 300 && $last_sync > 0): ?>
                    Sync terakhir <?= $time_diff ?>s lalu — data akan dihapus otomatis
                <?php elseif ($time_diff >= 300 && $last_sync > 0): ?>
                    Data dihapus karena Termux tidak aktif
                <?php else: ?>
                    Jalankan bot.py di Termux untuk sinkronisasi
                <?php endif; ?>
            </span>
        </div>
    <?php }
    return ob_get_clean();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>WnXD37</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    :root{
        --void:#07040a;
        --void-2:#0c0510;
        --crimson:#ff2d55;
        --crimson-2:#ff375f;
        --blood:#8b0020;
        --maroon:#5c0f24;
        --ember:#ff6b6b;
        --magenta:#c026a0;
        --glass:rgba(255,255,255,.06);
        --glass-2:rgba(255,255,255,.09);
        --glass-border:rgba(255,255,255,.12);
        --ink:#f6eef1;
        --ink-dim:rgba(246,238,241,.62);
        --ink-faint:rgba(246,238,241,.38);
        --green:#30D158;
        --orange:#FF9F0A;
        --gray:#9a8f93;
        --r-lg:26px;
        --r-md:18px;
        --r-sm:13px;
        --sidebar-w:288px;
        --font-ui:-apple-system,BlinkMacSystemFont,"SF Pro Display","Segoe UI",Roboto,Helvetica,Arial,sans-serif;
        --font-mono:ui-monospace,"SF Mono",Menlo,Consolas,monospace;
    }
    *{box-sizing:border-box;-webkit-tap-highlight-color:transparent;}
    html,body{margin:0;padding:0;}
    body{
        font-family:var(--font-ui);
        background:var(--void);
        color:var(--ink);
        min-height:100vh;
        overflow-x:hidden;
        position:relative;
    }
    :focus-visible{outline:2px solid var(--crimson-2);outline-offset:2px;border-radius:6px;}
    code{font-family:var(--font-mono);}

    /* ===================== AURORA BACKGROUND ===================== */
    .aurora-bg{position:fixed;inset:0;overflow:hidden;z-index:0;background:
        radial-gradient(ellipse at 20% 0%, #1a0410 0%, transparent 55%),
        radial-gradient(ellipse at 90% 100%, #150308 0%, transparent 55%),
        var(--void);}
    .blob{position:absolute;border-radius:50%;filter:blur(90px);mix-blend-mode:screen;opacity:.55;will-change:transform;}
    .blob-1{width:560px;height:560px;top:-120px;left:-100px;background:radial-gradient(circle,var(--crimson) 0%,transparent 70%);animation:drift1 26s ease-in-out infinite alternate;}
    .blob-2{width:640px;height:640px;top:10%;right:-160px;background:radial-gradient(circle,var(--maroon) 0%,transparent 70%);animation:drift2 34s ease-in-out infinite alternate;}
    .blob-3{width:480px;height:480px;bottom:-140px;left:15%;background:radial-gradient(circle,var(--blood) 0%,transparent 70%);animation:drift3 30s ease-in-out infinite alternate;}
    .blob-4{width:420px;height:420px;bottom:5%;right:10%;background:radial-gradient(circle,var(--magenta) 0%,transparent 70%);animation:drift4 22s ease-in-out infinite alternate;opacity:.4;}
    .blob-5{width:380px;height:380px;top:40%;left:40%;background:radial-gradient(circle,var(--ember) 0%,transparent 70%);animation:drift5 38s ease-in-out infinite alternate;opacity:.3;}
    @keyframes drift1{from{transform:translate(0,0) scale(1);}to{transform:translate(80px,60px) scale(1.15);}}
    @keyframes drift2{from{transform:translate(0,0) scale(1);}to{transform:translate(-70px,80px) scale(.9);}}
    @keyframes drift3{from{transform:translate(0,0) scale(1);}to{transform:translate(60px,-50px) scale(1.1);}}
    @keyframes drift4{from{transform:translate(0,0) scale(1);}to{transform:translate(-50px,-40px) scale(1.2);}}
    @keyframes drift5{from{transform:translate(-30px,-20px) scale(.9);}to{transform:translate(40px,30px) scale(1.05);}}

    /* ===================== GLASS PRIMITIVES ===================== */
    .glass-panel{
        background:linear-gradient(135deg,rgba(255,255,255,.08),rgba(255,255,255,.025));
        backdrop-filter:blur(28px) saturate(180%);
        -webkit-backdrop-filter:blur(28px) saturate(180%);
        border:1px solid var(--glass-border);
        border-radius:var(--r-lg);
        box-shadow:0 8px 32px rgba(0,0,0,.45), inset 0 1px 0 rgba(255,255,255,.09);
    }
    .glass-panel-sm{
        background:var(--glass);
        backdrop-filter:blur(20px) saturate(160%);
        -webkit-backdrop-filter:blur(20px) saturate(160%);
        border:1px solid var(--glass-border);
        border-radius:var(--r-md);
    }

    /* ===================== APP SHELL ===================== */
    .app-shell{position:relative;z-index:1;display:flex;min-height:100vh;}
    .drawer-overlay{position:fixed;inset:0;background:rgba(5,2,6,.6);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .3s ease;z-index:190;}
    .drawer-overlay.show{opacity:1;pointer-events:all;}

    /* ---- Sidebar ---- */
    .sidebar{
        width:var(--sidebar-w);flex:0 0 var(--sidebar-w);
        margin:18px 0 18px 18px;
        padding:22px 16px;
        display:flex;flex-direction:column;gap:22px;
        background:linear-gradient(160deg,rgba(255,255,255,.07),rgba(255,255,255,.02));
        backdrop-filter:blur(30px) saturate(180%);
        -webkit-backdrop-filter:blur(30px) saturate(180%);
        border:1px solid var(--glass-border);
        border-radius:28px;
        box-shadow:0 8px 40px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.08);
        position:sticky;top:18px;align-self:flex-start;
        height:calc(100vh - 36px);
    }
    .sidebar-header{display:flex;align-items:center;gap:12px;padding:0 4px;}
    .app-icon{
        width:44px;height:44px;border-radius:14px;flex:0 0 44px;
        background:linear-gradient(135deg,var(--crimson),var(--blood));
        display:flex;align-items:center;justify-content:center;font-size:19px;color:#fff;
        box-shadow:0 4px 16px rgba(255,45,85,.4);
    }
    .app-title{font-weight:700;font-size:15.5px;letter-spacing:.2px;}
    .app-subtitle{font-size:11.5px;color:var(--ink-faint);margin-top:1px;}
    .drawer-close{display:none;margin-left:auto;background:none;border:none;color:var(--ink-dim);font-size:26px;line-height:1;cursor:pointer;padding:2px 6px;}

    .sidebar-nav{display:flex;flex-direction:column;gap:8px;}
    .nav-item{
        display:flex;align-items:center;gap:13px;
        padding:13px 14px;border-radius:var(--r-md);
        background:transparent;border:1px solid transparent;
        cursor:pointer;text-align:left;transition:background .25s ease,border-color .25s ease,transform .15s ease;
        color:var(--ink);
    }
    .nav-item:hover{background:var(--glass);}
    .nav-item:active{transform:scale(.98);}
    .nav-item.active{background:linear-gradient(135deg,rgba(255,45,85,.22),rgba(139,0,32,.12));border-color:rgba(255,55,95,.35);box-shadow:0 4px 18px rgba(255,45,85,.15);}
    .nav-icon{
        width:36px;height:36px;border-radius:11px;flex:0 0 36px;
        display:flex;align-items:center;justify-content:center;
        background:rgba(255,255,255,.06);font-size:15px;color:var(--ink-dim);
        transition:background .25s ease,color .25s ease;
    }
    .nav-item.active .nav-icon{background:linear-gradient(135deg,var(--crimson-2),var(--blood));color:#fff;}
    .nav-text{display:flex;flex-direction:column;gap:2px;min-width:0;}
    .nav-title{font-size:14px;font-weight:600;}
    .nav-desc{font-size:11.5px;color:var(--ink-faint);white-space:normal;line-height:1.3;}
    .nav-item.active .nav-desc{color:rgba(246,238,241,.72);}

    .sidebar-footer{margin-top:auto;padding:12px 14px;border-radius:var(--r-md);background:var(--glass);border:1px solid var(--glass-border);}
    .sidebar-footer-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--ink-faint);margin-bottom:4px;}
    .sidebar-footer-path{font-family:var(--font-mono);font-size:10.5px;color:var(--ink-dim);word-break:break-all;}

    /* ---- Main content ---- */
    .main-content{flex:1;min-width:0;padding:18px 22px 40px;display:flex;flex-direction:column;gap:20px;}

    .topbar{display:flex;align-items:center;gap:14px;padding:14px 18px;flex-wrap:wrap;}
    .status-indicator{display:flex;align-items:center;gap:12px;flex:1;min-width:220px;}
    .status-pulse{width:11px;height:11px;border-radius:50%;flex:0 0 11px;position:relative;}
    .status-pulse.online{background:var(--green);box-shadow:0 0 0 0 rgba(48,209,88,.6);animation:pulseRing 2s ease-out infinite;}
    .status-pulse.offline{background:#ff453a;box-shadow:0 0 0 0 rgba(255,69,58,.5);animation:pulseRing 2.4s ease-out infinite;}
    @keyframes pulseRing{0%{box-shadow:0 0 0 0 rgba(255,255,255,.35);}70%{box-shadow:0 0 0 10px rgba(255,255,255,0);}100%{box-shadow:0 0 0 0 rgba(255,255,255,0);}}
    .status-text-group{display:flex;flex-direction:column;gap:1px;}
    .status-title{font-size:14px;font-weight:600;}
    .status-sub{font-size:11.5px;color:var(--ink-faint);}
    .topbar-actions{display:flex;align-items:center;gap:8px;}
    .hamburger-btn{display:none;}

    .panel-heading{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;}
    .panel-heading h1{font-size:21px;margin:0 0 4px;font-weight:700;letter-spacing:.1px;}
    .panel-heading p{margin:0;font-size:13px;color:var(--ink-faint);}
    .panel-heading code{font-size:11.5px;background:var(--glass);padding:2px 6px;border-radius:6px;}

    .tab-panel{display:none;flex-direction:column;gap:18px;}
    .tab-panel.active{display:flex;animation:panelIn .4s cubic-bezier(.22,1,.36,1) forwards;}
    @keyframes panelIn{from{opacity:0;transform:translateY(10px);}to{opacity:1;transform:translateY(0);}}

    /* ===================== BUTTONS ===================== */
    .btn-primary{
        display:inline-flex;align-items:center;gap:8px;justify-content:center;
        padding:12px 20px;border:none;border-radius:14px;cursor:pointer;
        font-family:var(--font-ui);font-weight:600;font-size:13.5px;color:#fff;
        background:linear-gradient(135deg,var(--crimson-2),var(--blood));
        box-shadow:0 6px 20px rgba(255,45,85,.35);
        transition:transform .15s ease,box-shadow .2s ease;
    }
    .btn-primary:hover{box-shadow:0 8px 26px rgba(255,45,85,.48);}
    .btn-primary:active{transform:scale(.96);}
    .btn-primary.sm{padding:9px 15px;font-size:12.5px;border-radius:12px;}

    .icon-btn-outline{
        display:inline-flex;align-items:center;gap:7px;
        padding:9px 14px;border-radius:12px;cursor:pointer;
        background:var(--glass);border:1px solid var(--glass-border);color:var(--ink);
        font-family:var(--font-ui);font-size:12.5px;font-weight:600;
        transition:background .2s ease,transform .15s ease;
    }
    .icon-btn-outline:hover{background:var(--glass-2);}
    .icon-btn-outline:active{transform:scale(.95);}
    .icon-btn-outline.danger{color:#ff8a8a;}
    .icon-btn-outline.danger:hover{background:rgba(255,69,58,.14);}

    .hamburger-btn{
        width:38px;height:38px;border-radius:12px;border:1px solid var(--glass-border);
        background:var(--glass);color:var(--ink);cursor:pointer;align-items:center;justify-content:center;font-size:15px;
    }
    .hamburger-btn:active{transform:scale(.93);}

    /* ===================== iOS TOGGLE ===================== */
    .ios-toggle{position:relative;width:50px;height:30px;display:inline-block;flex:0 0 50px;cursor:pointer;}
    .ios-toggle input{position:absolute;opacity:0;width:100%;height:100%;margin:0;cursor:pointer;z-index:2;}
    .ios-toggle .track{position:absolute;inset:0;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.1);border-radius:999px;transition:background .3s ease,box-shadow .3s ease;}
    .ios-toggle input:checked ~ .track{background:linear-gradient(135deg,var(--crimson-2),var(--blood));box-shadow:0 0 14px rgba(255,55,95,.55);}
    .ios-toggle .thumb{position:absolute;top:2px;left:2px;width:26px;height:26px;border-radius:50%;background:#fff;box-shadow:0 2px 6px rgba(0,0,0,.35);transition:transform .3s cubic-bezier(.4,0,.2,1);}
    .ios-toggle input:checked ~ .thumb{transform:translateX(20px);}

    /* ===================== LIST ACCOUNT TAB ===================== */
    .auto-rejoin-pill{display:flex;align-items:center;gap:12px;padding:10px 16px;}
    .auto-rejoin-text{font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;color:var(--ink-dim);}
    .auto-rejoin-status{font-size:11.5px;font-weight:700;color:var(--ink-faint);min-width:26px;}

    .account-list{display:flex;flex-direction:column;gap:12px;}
    .account-card{
        padding:16px 18px;display:flex;flex-direction:column;gap:14px;
        animation:cardIn .5s cubic-bezier(.22,1,.36,1) backwards;
        animation-delay:calc(var(--i,0) * 55ms);
        transition:box-shadow .25s ease,border-color .25s ease;
    }
    .account-card:hover{border-color:rgba(255,55,95,.3);}
    @keyframes cardIn{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:translateY(0);}}

    .account-main{display:flex;align-items:center;gap:14px;}
    .account-avatar{position:relative;width:46px;height:46px;flex:0 0 46px;border-radius:15px;background:linear-gradient(135deg,var(--crimson),var(--maroon));display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(255,45,85,.3);}
    .account-card:nth-child(3n+2) .account-avatar{background:linear-gradient(135deg,var(--magenta),var(--blood));}
    .account-card:nth-child(3n+3) .account-avatar{background:linear-gradient(135deg,var(--ember),var(--maroon));}
    .avatar-initial{color:#fff;font-weight:700;font-size:17px;}
    .status-dot{position:absolute;bottom:-2px;right:-2px;width:14px;height:14px;border-radius:50%;border:2.5px solid var(--void-2);}
    .status-dot.online{background:var(--green);}
    .status-dot.offline{background:#6b6b6b;}

    .account-info{min-width:0;flex:1;display:flex;flex-direction:column;gap:3px;}
    .account-username{font-size:14.5px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .account-pkg{font-family:var(--font-mono);font-size:10.5px;color:var(--ink-faint);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .cmd-badge{display:flex;align-items:center;gap:6px;font-size:11.5px;color:var(--ink-dim);margin-top:2px;flex-wrap:wrap;}
    .cmd-badge i{font-size:9px;color:var(--crimson-2);}
    .exec-badge{display:inline-flex;align-items:center;gap:4px;color:var(--green);font-size:11px;}

    .account-actions{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;}
    .icon-btn{
        display:flex;flex-direction:column;align-items:center;gap:4px;
        padding:9px 4px;border-radius:13px;border:1px solid var(--glass-border);
        background:rgba(255,255,255,.05);color:var(--ink-dim);cursor:pointer;
        font-family:var(--font-ui);font-size:10px;font-weight:600;
        transition:transform .15s ease,background .2s ease,color .2s ease,border-color .2s ease;
    }
    .icon-btn i{font-size:14px;}
    .icon-btn:active{transform:scale(.92);}
    .icon-btn.start:hover{background:rgba(48,209,88,.14);border-color:rgba(48,209,88,.4);color:var(--green);}
    .icon-btn.rerun:hover{background:rgba(255,159,10,.14);border-color:rgba(255,159,10,.4);color:var(--orange);}
    .icon-btn.stop:hover{background:rgba(255,69,58,.14);border-color:rgba(255,69,58,.4);color:#ff6b6b;}
    .icon-btn.idle:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.25);color:#fff;}

    .target-hint{
        display:flex;align-items:center;gap:7px;justify-content:center;
        background:none;border:none;color:var(--ink-faint);font-size:11px;
        cursor:pointer;padding:6px;border-top:1px dashed var(--glass-border);
        font-family:var(--font-ui);transition:color .2s ease;
    }
    .target-hint:hover{color:var(--crimson-2);}

    .list-footer{text-align:center;font-size:12px;color:var(--ink-faint);padding:4px 0;}

    /* ===================== EMPTY STATES ===================== */
    .empty-state{padding:44px 20px;text-align:center;color:var(--ink-faint);}
    .empty-state i{font-size:40px;color:rgba(255,55,95,.45);margin-bottom:14px;display:block;}
    .empty-state p{margin:0 0 6px;font-size:14px;color:var(--ink-dim);}
    .empty-state span{font-size:12px;}
    .empty-state code{display:inline-block;margin-top:10px;background:var(--glass);padding:8px 16px;border-radius:10px;font-size:12px;}
    .empty-state-sm{padding:30px 16px;text-align:center;color:var(--ink-faint);font-size:12.5px;}
    .empty-state-sm i{font-size:28px;color:rgba(255,55,95,.4);margin-bottom:10px;display:block;}

    /* ===================== GLOBAL SETTINGS TAB ===================== */
    .settings-card{padding:24px;display:flex;flex-direction:column;gap:16px;max-width:460px;}
    .field-group{display:flex;flex-direction:column;gap:7px;}
    .field-label{font-size:12px;font-weight:600;color:var(--ink-dim);text-transform:uppercase;letter-spacing:.05em;}
    .field-hint{font-size:11.5px;color:var(--ink-faint);line-height:1.5;margin:2px 0 0;}

    .input-glass{display:flex;align-items:center;gap:10px;background:var(--glass);border:1px solid var(--glass-border);border-radius:14px;padding:12px 14px;transition:border-color .2s ease,background .2s ease;}
    .input-glass:focus-within{border-color:rgba(255,55,95,.5);background:var(--glass-2);}
    .input-glass i{color:var(--ink-faint);font-size:13px;}
    .input-glass input{flex:1;background:none;border:none;outline:none;color:var(--ink);font-family:var(--font-ui);font-size:13.5px;}
    .input-glass input::placeholder{color:var(--ink-faint);}

    .textarea-glass{width:100%;background:var(--glass);border:1px solid var(--glass-border);border-radius:14px;padding:12px 14px;color:var(--ink);font-family:var(--font-mono);font-size:12.5px;outline:none;resize:vertical;transition:border-color .2s ease;}
    .textarea-glass:focus{border-color:rgba(255,55,95,.5);}

    /* ---- Custom dropdown ---- */
    .dropdown{position:relative;}
    .dropdown-trigger{
        width:100%;display:flex;align-items:center;gap:10px;
        background:var(--glass);border:1px solid var(--glass-border);border-radius:14px;
        padding:12px 14px;cursor:pointer;color:var(--ink);font-family:var(--font-ui);font-size:13.5px;
        transition:border-color .2s ease,background .2s ease;
    }
    .dropdown-trigger:hover{background:var(--glass-2);}
    .dropdown.open .dropdown-trigger{border-color:rgba(255,55,95,.5);}
    .dropdown-trigger i:first-child{color:var(--ink-faint);font-size:13px;}
    .dropdown-label{flex:1;text-align:left;}
    .dropdown-caret{font-size:11px;color:var(--ink-faint);transition:transform .25s ease;}
    .dropdown.open .dropdown-caret{transform:rotate(180deg);}
    .dropdown-menu{
        position:absolute;top:calc(100% + 8px);left:0;right:0;z-index:40;
        background:linear-gradient(160deg,rgba(20,8,14,.92),rgba(12,4,10,.92));
        backdrop-filter:blur(24px) saturate(180%);-webkit-backdrop-filter:blur(24px) saturate(180%);
        border:1px solid var(--glass-border);border-radius:16px;padding:6px;
        box-shadow:0 12px 32px rgba(0,0,0,.5);
        opacity:0;transform:translateY(-8px) scale(.96);pointer-events:none;
        transition:opacity .22s cubic-bezier(.22,1,.36,1),transform .22s cubic-bezier(.22,1,.36,1);
    }
    .dropdown.open .dropdown-menu{opacity:1;transform:translateY(0) scale(1);pointer-events:all;}
    .dropdown-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:11px;cursor:pointer;font-size:13px;color:var(--ink-dim);transition:background .15s ease,color .15s ease;}
    .dropdown-item:hover{background:var(--glass);color:var(--ink);}
    .dropdown-item.selected{color:var(--crimson-2);font-weight:600;background:rgba(255,55,95,.12);}
    .dropdown-item i{font-size:13px;width:16px;text-align:center;}

    /* ===================== AUTO EXECUTE TAB ===================== */
    .autoexec-actions{display:flex;gap:10px;flex-wrap:wrap;}
    .file-list{padding:10px;display:flex;flex-direction:column;gap:8px;min-height:80px;}
    .file-row{display:flex;align-items:center;gap:13px;padding:12px 14px;border-radius:14px;background:rgba(255,255,255,.03);border:1px solid transparent;transition:background .2s ease,border-color .2s ease;}
    .file-row:hover{background:var(--glass);border-color:var(--glass-border);}
    .file-icon{width:38px;height:38px;flex:0 0 38px;border-radius:11px;background:linear-gradient(135deg,var(--crimson),var(--blood));display:flex;align-items:center;justify-content:center;color:#fff;font-size:14px;}
    .file-info{min-width:0;flex:1;}
    .file-name{font-size:13.5px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .file-meta{font-size:11px;color:var(--ink-faint);margin-top:2px;}
    .file-actions{display:flex;gap:6px;}
    .icon-btn-sm{width:32px;height:32px;border-radius:10px;border:1px solid var(--glass-border);background:var(--glass);color:var(--ink-dim);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:12px;transition:background .2s ease,color .2s ease,transform .15s ease;}
    .icon-btn-sm:hover{background:var(--glass-2);color:var(--ink);}
    .icon-btn-sm:active{transform:scale(.9);}
    .icon-btn-sm.danger:hover{background:rgba(255,69,58,.16);color:#ff6b6b;}

    /* ===================== MODAL ===================== */
    .modal-overlay{position:fixed;inset:0;background:rgba(5,2,6,.68);backdrop-filter:blur(6px);display:none;align-items:center;justify-content:center;z-index:300;padding:20px;}
    .modal-overlay.show{display:flex;animation:overlayIn .25s ease forwards;}
    @keyframes overlayIn{from{opacity:0;}to{opacity:1;}}
    .modal-glass{width:100%;max-width:520px;padding:24px;animation:modalIn .3s cubic-bezier(.22,1,.36,1) forwards;}
    @keyframes modalIn{from{opacity:0;transform:translateY(16px) scale(.97);}to{opacity:1;transform:translateY(0) scale(1);}}
    .modal-glass h3{margin:0 0 16px;font-size:16px;font-weight:700;}
    .modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:6px;}

    /* ===================== TOAST ===================== */
    .toast-container{position:fixed;left:0;right:0;bottom:22px;display:flex;flex-direction:column;align-items:center;gap:10px;z-index:400;pointer-events:none;padding:0 16px;}
    .toast{
        pointer-events:all;display:flex;align-items:center;gap:10px;
        padding:13px 18px;border-radius:16px;max-width:420px;width:100%;
        background:linear-gradient(135deg,rgba(24,10,15,.92),rgba(14,5,10,.92));
        backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);
        border:1px solid var(--glass-border);box-shadow:0 10px 30px rgba(0,0,0,.5);
        font-size:13px;color:var(--ink);
        animation:toastIn .35s cubic-bezier(.22,1,.36,1) forwards;
    }
    .toast.hide{animation:toastOut .3s ease forwards;}
    .toast.success{border-left:3px solid var(--green);}
    .toast.error{border-left:3px solid #ff453a;}
    .toast.info{border-left:3px solid var(--crimson-2);}
    .toast i{font-size:14px;flex:0 0 auto;}
    .toast.success i{color:var(--green);}
    .toast.error i{color:#ff453a;}
    .toast.info i{color:var(--crimson-2);}
    @keyframes toastIn{from{opacity:0;transform:translateY(18px) scale(.94);}to{opacity:1;transform:translateY(0) scale(1);}}
    @keyframes toastOut{from{opacity:1;transform:translateY(0) scale(1);}to{opacity:0;transform:translateY(10px) scale(.94);}}

    /* ===================== RESPONSIVE ===================== */
    @media (max-width:960px){
        .sidebar{
            position:fixed;top:0;left:0;height:100vh;margin:0;
            width:82vw;max-width:320px;flex-basis:auto;
            border-radius:0 26px 26px 0;
            transform:translateX(-100%);
            transition:transform .35s cubic-bezier(.22,1,.36,1);
            z-index:200;
        }
        .sidebar.open{transform:translateX(0);}
        .drawer-close{display:block;}
        .hamburger-btn{display:flex;}
        .main-content{padding:16px 14px 32px;}
        .account-actions{grid-template-columns:repeat(4,1fr);}
        .settings-card{max-width:100%;}
    }
    @media (max-width:480px){
        .panel-heading{align-items:flex-start;}
        .icon-btn span{font-size:9px;}
        .topbar{padding:12px 14px;}
    }

    @media (prefers-reduced-motion:reduce){
        *{animation-duration:.01ms !important;animation-iteration-count:1 !important;transition-duration:.01ms !important;}
    }
</style>
</head>
<body>

<div class="aurora-bg" aria-hidden="true">
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="blob blob-3"></div>
    <div class="blob blob-4"></div>
    <div class="blob blob-5"></div>
</div>


<div class="app-shell">
    <!-- ===================== SIDEBAR ===================== -->
    <div id="drawerOverlay" class="drawer-overlay"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="app-icon"><i class="fas fa-skull"></i></div>
            <div>
                <div class="app-title">WnXD1337</div>
                <div class="app-subtitle">Multi Akun Kontoler</div>
            </div>
            <button class="drawer-close" id="drawerClose" aria-label="Tutup menu">&times;</button>
        </div>
        <nav class="sidebar-nav">
            <button class="nav-item active" data-tab="accounts" type="button">
                <span class="nav-icon"><i class="fas fa-list"></i></span>
                <span class="nav-text">
                    <span class="nav-title">List Account</span>
                    <span class="nav-desc">Akun terhubung &amp; toggle auto rejoin</span>
                </span>
            </button>
            <button class="nav-item" data-tab="settings" type="button">
                <span class="nav-icon"><i class="fas fa-sliders-h"></i></span>
                <span class="nav-text">
                    <span class="nav-title">Global Settings</span>
                    <span class="nav-desc">Settings Private Server / Public Game</span>
                </span>
            </button>
            <button class="nav-item" data-tab="autoexec" type="button">
                <span class="nav-icon"><i class="fas fa-code"></i></span>
                <span class="nav-text">
                    <span class="nav-title">Auto Execute</span>
                    <span class="nav-desc">Setup Auto Execute For Delta</span>
                </span>
            </button>
        </nav>
        <div class="sidebar-footer">
            <div class="sidebar-footer-label">Autoexecute Path</div>
            <div class="sidebar-footer-path">/storage/emulated/0/Delta/Autoexecute</div>
        </div>
    </aside>

    <!-- ===================== MAIN CONTENT ===================== -->
    <main class="main-content">
        <!-- STATUS KONEKSI (selalu tampil di semua tab) -->
        <div class="topbar glass-panel">
            <div class="status-indicator" id="connectionStatus">
                <?= render_status_indicator($is_connected, $account_count, $time_diff, $last_sync) ?>
            </div>
            <div class="topbar-actions">
                <button class="icon-btn-outline" onclick="location.reload()" title="Refresh Status"><i class="fas fa-sync"></i></button>
                <button class="icon-btn-outline danger" onclick="clearAllData()" title="Clear All Data"><i class="fas fa-trash"></i></button>
                <button class="hamburger-btn" id="hamburgerBtn" aria-label="Buka menu"><i class="fas fa-bars"></i></button>
            </div>
        </div>

        <!-- ===================== TAB 1: LIST ACCOUNT ===================== -->
        <section class="tab-panel active" id="tab-accounts" data-panel="accounts">
            <div class="panel-heading">
                <div>
                    <h1>List Account</h1>
                </div>
                <div class="auto-rejoin-pill glass-panel-sm">
                    <span class="auto-rejoin-text"><i class="fas fa-rotate-right"></i> Auto Rejoin</span>
                    <label class="ios-toggle">
                        <input type="checkbox" id="autoRejoinToggle" <?= $auto_rejoin ? 'checked' : '' ?> onchange="toggleAutoRejoin(this.checked)">
                        <span class="track"></span>
                        <span class="thumb"></span>
                    </label>
                    <span class="auto-rejoin-status" id="autoRejoinStatus"><?= $auto_rejoin ? 'ON' : 'OFF' ?></span>
                </div>
            </div>

            <div class="account-list" id="accountList">
                <?php if (!$is_connected): ?>
                    <div class="empty-state glass-panel">
                        <i class="fas fa-robot"></i>
                        <p>Menunggu koneksi Termux...</p>
                        <span>Jalankan bot.py di Termux dengan perintah:</span><br>
                        <code>python3 bot.py</code>
                    </div>
                <?php elseif ($account_count === 0): ?>
                    <div class="empty-state glass-panel">
                        <i class="fas fa-inbox"></i>
                        <p>Tidak ada akun yang terdeteksi.</p>
                    </div>
                <?php else:
                    $i = 0;
                    foreach ($active_commands as $pkg => $cmd_info):
                        if ($pkg === '_file_manager') continue;
                        $is_running = $status_data[$pkg]['running'] ?? false;
                        $username = $status_data[$pkg]['username'] ?? 'Unknown';
                        $executed = $cmd_info['executed'] ?? false;
                        $current_cmd = $cmd_info['cmd'] ?? 'IDLE';
                        $initial = strtoupper(substr($username, 0, 1));
                        $i++;
                ?>
                <div class="account-card glass-panel" data-pkg="<?= $pkg ?>" style="--i:<?= $i ?>;">
                    <div class="account-main">
<div class="account-avatar" id="avatar-container-<?= $pkg ?>">
    <!-- Tambahkan tag img yang awalnya disembunyikan -->
    <img class="avatar-img" id="avatar-img-<?= $pkg ?>" src="" alt="Avatar" style="display:none; width:100%; height:100%; border-radius:15px; object-fit:cover;">
    
    <span class="avatar-initial" id="avatar-initial-<?= $pkg ?>"><?= htmlspecialchars($initial) ?></span>
    <span class="status-dot <?= $is_running ? 'online' : 'offline' ?>"></span>
</div>

                        <div class="account-info">
                            <div class="account-username"><?= htmlspecialchars($username) ?></div>
                            <div class="account-pkg"><?= htmlspecialchars($pkg) ?></div>
                            <div class="cmd-badge">
                                <i class="fas fa-circle"></i>
                                <span class="cmd-badge-text"><?= htmlspecialchars($current_cmd) ?></span>
                                <?php if ($executed): ?><span class="exec-badge"><i class="fas fa-check"></i> selesai</span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="account-actions">
                        <button class="icon-btn start" onclick="executeCmd('<?= $pkg ?>', 'START')" title="Start"><i class="fas fa-play"></i><span>Start</span></button>
                        <button class="icon-btn rerun" onclick="executeCmd('<?= $pkg ?>', 'RERUN')" title="Rerun"><i class="fas fa-redo"></i><span>Rerun</span></button>
                        <button class="icon-btn stop" onclick="executeCmd('<?= $pkg ?>', 'STOP')" title="Stop"><i class="fas fa-stop"></i><span>Stop</span></button>
                        <button class="icon-btn idle" onclick="executeCmd('<?= $pkg ?>', 'IDLE')" title="Idle"><i class="fas fa-pause"></i><span>Idle</span></button>
                    </div>
                    <button class="target-hint" type="button" onclick="switchTab('settings')">
                        <i class="fas fa-sliders-h"></i> Set target &amp; mode di Global Settings
                    </button>
                </div>
                <?php endforeach; endif; ?>
            </div>

            <?php if ($is_connected && $account_count > 0): ?>
            <div class="list-footer"><i class="fas fa-info-circle"></i> 37 Corp</div>
            <?php endif; ?>
        </section>

        <section class="tab-panel" id="tab-settings" data-panel="settings">
            <div class="panel-heading">
                <div>
                    <h1>Global Settings</h1>
                    <p>Mode &amp; target di sini dipakai seluruh akun saat dieksekusi.</p>
                </div>
            </div>
            <div class="settings-card glass-panel">
                <div class="field-group">
                    <label class="field-label">Mode</label>
                    <div class="dropdown" id="modeDropdown">
                        <input type="hidden" id="globalModeValue" value="public">
                        <button type="button" class="dropdown-trigger" id="modeDropdownTrigger">
                            <i class="fas fa-globe"></i>
                            <span class="dropdown-label">Public (Place ID)</span>
                            <i class="fas fa-chevron-down dropdown-caret"></i>
                        </button>
                        <div class="dropdown-menu">
                            <div class="dropdown-item selected" data-value="public"><i class="fas fa-globe"></i> Public (Place ID)</div>
                            <div class="dropdown-item" data-value="private"><i class="fas fa-lock"></i> Private (Link/Code)</div>
                        </div>
                    </div>
                </div>
                <div class="field-group">
                    <label class="field-label" for="globalTarget">Target</label>
                    <div class="input-glass">
                        <i class="fas fa-link"></i>
                        <input type="text" id="globalTarget" placeholder="Masukkan Place ID / Link">
                    </div>
                </div>
                <button class="btn-primary" type="button" onclick="applyAll()"><i class="fas fa-check"></i> Apply to All</button>
                <p class="field-hint">Setting ini otomatis dipakai saat kamu menekan Start / Rerun pada akun mana pun di tab List Account. Tombol Apply to All mendorong perubahan ke seluruh akun sekaligus.</p>
            </div>
        </section>

        <!-- ===================== TAB 3: AUTO EXECUTE ===================== -->
        <section class="tab-panel" id="tab-autoexec" data-panel="autoexec">
            <div class="panel-heading">
                <div>
                    <h1>Auto Execute</h1>
                    <p>Manage script in <code>/storage/emulated/0/Delta/Autoexecute</code></p>
                </div>
                <div class="autoexec-actions">
                    <button class="icon-btn-outline" type="button" onclick="refreshFileList()"><i class="fas fa-sync"></i> Refresh</button>
                    <button class="btn-primary sm" type="button" onclick="showAddFileModal()"><i class="fas fa-plus"></i> Tambah File</button>
                </div>
            </div>
            <div class="file-list glass-panel" id="fileListContainer">
                <div class="empty-state-sm">
                    <i class="fas fa-folder-open"></i>
                    <p>Klik "Refresh" untuk memuat daftar file.</p>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- MODAL TAMBAH/EDIT FILE -->
<div id="fileModal" class="modal-overlay">
    <div class="modal-glass glass-panel">
        <h3 id="fileModalTitle">Tambah File</h3>
        <form id="fileForm">
            <input type="hidden" id="fileOperation" value="FILE_ADD">
            <div class="field-group" style="margin-bottom:14px;">
                <label class="field-label">Nama File</label>
                <div class="input-glass"><i class="fas fa-file"></i><input type="text" id="fileName" required></div>
            </div>
            <div class="field-group">
                <label class="field-label">Konten</label>
                <textarea id="fileContent" class="textarea-glass" rows="10"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="icon-btn-outline" onclick="closeFileModal()">Batal</button>
                <button type="submit" class="btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toastContainer"></div>

<script>
// ========== HELPER: API REQUEST ==========
async function apiRequest(action, data = null, method = 'POST') {
    const url = `api.php?action=${action}`;
    const options = { method: method, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } };
    if (data) {
        options.body = new URLSearchParams(data);
    }
    const response = await fetch(url, options);
    return await response.json();
}

// ========== TOAST NOTIFICATION ==========
function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'fa-circle-check' : (type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-info');
    toast.innerHTML = `<i class="fas ${icon}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 320);
    }, 3400);
}

// ========== SIDEBAR / TAB NAVIGATION ==========
function switchTab(tabId) {
    document.querySelectorAll('.nav-item').forEach(btn => btn.classList.toggle('active', btn.dataset.tab === tabId));
    document.querySelectorAll('.tab-panel').forEach(panel => panel.classList.toggle('active', panel.dataset.panel === tabId));
    closeDrawer();
}
document.querySelectorAll('.nav-item').forEach(btn => {
    btn.addEventListener('click', () => switchTab(btn.dataset.tab));
});

function openDrawer() {
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('drawerOverlay').classList.add('show');
}
function closeDrawer() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('drawerOverlay').classList.remove('show');
}
document.getElementById('hamburgerBtn').addEventListener('click', openDrawer);
document.getElementById('drawerClose').addEventListener('click', closeDrawer);
document.getElementById('drawerOverlay').addEventListener('click', closeDrawer);

// ========== CUSTOM DROPDOWN (MODE PUBLIC/PRIVATE) ==========
function initDropdown(rootId) {
    const root = document.getElementById(rootId);
    const trigger = root.querySelector('.dropdown-trigger');
    const menu = root.querySelector('.dropdown-menu');
    const valueInput = root.querySelector('input[type="hidden"]');
    const labelSpan = root.querySelector('.dropdown-label');

    function toggleOpen(e) {
        if (e) e.stopPropagation();
        document.querySelectorAll('.dropdown.open').forEach(d => { if (d !== root) d.classList.remove('open'); });
        root.classList.toggle('open');
    }
    trigger.addEventListener('click', toggleOpen);
    trigger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleOpen(e); }
    });
    menu.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', () => {
            valueInput.value = item.dataset.value;
            // Menyimpan ikon dan teks menggunakan innerHTML agar ikon font-awesome tidak hilang
            labelSpan.innerHTML = item.innerHTML; 
            menu.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');
            root.classList.remove('open');
            
            // SIMPAN KE LOCAL STORAGE
            localStorage.setItem('globalMode', item.dataset.value);
            localStorage.setItem('globalModeHTML', item.innerHTML);
        });
    });
}
initDropdown('modeDropdown');
document.addEventListener('click', () => {
    document.querySelectorAll('.dropdown.open').forEach(d => d.classList.remove('open'));
});

// ========== LOCAL STORAGE: SIMPAN & MUAT TARGET ==========
const globalTargetInput = document.getElementById('globalTarget');

// Simpan saat user mengetik
globalTargetInput.addEventListener('input', (e) => {
    localStorage.setItem('globalTarget', e.target.value);
});

// Load data saat halaman pertama kali dimuat
document.addEventListener('DOMContentLoaded', () => {
    // 1. Muat Target
    const savedTarget = localStorage.getItem('globalTarget');
    if (savedTarget) {
        globalTargetInput.value = savedTarget;
    }

    // 2. Muat Mode
    const savedMode = localStorage.getItem('globalMode');
    const savedModeHTML = localStorage.getItem('globalModeHTML');
    
    if (savedMode && savedModeHTML) {
        const root = document.getElementById('modeDropdown');
        const valueInput = root.querySelector('input[type="hidden"]');
        const labelSpan = root.querySelector('.dropdown-label');
        const menu = root.querySelector('.dropdown-menu');

        valueInput.value = savedMode;
        labelSpan.innerHTML = savedModeHTML;

        // Perbarui visual item yang terpilih di dropdown
        menu.querySelectorAll('.dropdown-item').forEach(i => {
            i.classList.remove('selected');
            if(i.dataset.value === savedMode) {
                i.classList.add('selected');
            }
        });
    }
});

function getGlobalMode() { return document.getElementById('globalModeValue').value; }
function getGlobalTarget() { return document.getElementById('globalTarget').value.trim(); }

// ========== CLEAR ALL DATA ==========
async function clearAllData() {
    // Tampilkan konfirmasi SweetAlert
    const confirmResult = await Swal.fire({
        title: 'Delete All Data?',
        text: "Ur Status Account Will Be Reseted!.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff2d55', // var(--crimson)
        cancelButtonColor: 'rgba(255, 255, 255, 0.1)', // var(--glass)
        confirmButtonText: '<i class="fas fa-trash"></i> Yep!',
        cancelButtonText: 'Cancel',
        background: 'linear-gradient(135deg, rgba(20,8,14,.98), rgba(12,4,10,.98))',
        color: '#f6eef1', // var(--ink)
        backdrop: `rgba(5,2,6,0.7)` // Mengikuti modal-overlay bawaan
    });

    // Jika tombol batal diklik, hentikan fungsi
    if (!confirmResult.isConfirmed) return;

    // Lanjutkan proses penghapusan jika 'Ya'
    const result = await apiRequest('clear_installed');
    if (result.status === 'ok') {
        showToast('Data berhasil di-reset. Memuat ulang halaman...', 'success');
        setTimeout(() => location.reload(), 900);
    } else {
        showToast('Gagal mereset data.', 'error');
    }
}

// ========== EKSEKUSI PERINTAH AKUN (memakai Mode & Target dari Global Settings) ==========
async function executeCmd(pkg, cmd) {
    const mode = getGlobalMode();
    const target = getGlobalTarget();
    if ((cmd === 'START' || cmd === 'RERUN') && !target) {
        showToast('Atur Target di tab Global Settings dulu.', 'error');
        switchTab('settings');
        return;
    }
    const result = await apiRequest('set_cmd', { pkg: pkg, cmd: cmd, mode: mode, target: target });
    if (result.status === 'ok') {
        const card = document.querySelector(`.account-card[data-pkg="${pkg}"]`);
        if (card) {
            const label = card.querySelector('.cmd-badge-text');
            if (label) label.textContent = cmd;
        }
        showToast(cmd === 'IDLE' ? `Perintah IDLE untuk ${pkg} dikirim.` : `Perintah ${cmd} untuk ${pkg} dikirim.`, 'success');
    } else {
        showToast('Gagal mengirim perintah.', 'error');
    }
}

// ========== APPLY TO ALL (GLOBAL SETTINGS) ==========
async function applyAll() {
    const mode = getGlobalMode();
    const target = getGlobalTarget();
    if (!target) { showToast('Target tidak boleh kosong.', 'error'); return; }
    const result = await apiRequest('set_all_cmd', { mode: mode, target: target });
    if (result.status === 'ok') {
        showToast('Pengaturan global diterapkan ke semua akun.', 'success');
    } else {
        showToast(result.message || 'Gagal apply all.', 'error');
    }
}

// ========== AUTO REJOIN TOGGLE ==========
async function toggleAutoRejoin(enabled) {
    const result = await apiRequest('set_auto_rejoin', { enabled: enabled ? 'true' : 'false' });
    if (result.status === 'ok') {
        document.getElementById('autoRejoinStatus').textContent = enabled ? 'ON' : 'OFF';
    } else {
        showToast('Gagal mengubah setting auto rejoin.', 'error');
        document.getElementById('autoRejoinToggle').checked = !enabled;
    }
}

// ========== LIVE TICKER SYNC (update tiap detik tanpa nunggu polling) ==========
let lastSyncTs = <?= (int)$last_sync ?>;
let isConnectedFlag = <?= $is_connected ? 'true' : 'false' ?>;
setInterval(() => {
    if (!isConnectedFlag || !lastSyncTs) return;
    const diff = Math.floor(Date.now() / 1000) - lastSyncTs;
    const subEl = document.getElementById('statusSub');
    if (subEl) subEl.textContent = `Sync ${diff}s lalu`;
}, 1000);

// ========== POLLING DASHBOARD DATA (UPDATE REAL-TIME) ==========
async function pollDashboard() {
    try {
        const response = await fetch('api.php?action=get_dashboard_data');
        const data = await response.json();
        if (data.status !== 'ok') return;

        isConnectedFlag = data.is_connected;
        lastSyncTs = data.last_sync;

        // Update status koneksi
        const connDiv = document.getElementById('connectionStatus');
        const accCount = (data.installed || []).length;
        if (data.is_connected) {
            connDiv.innerHTML = `
                <span class="status-pulse online"></span>
                <div class="status-text-group">
                    <span class="status-title">Connected — ${accCount} </span>
                    <span class="status-sub" id="statusSub">Sync ${data.time_diff}s lalu</span>
                </div>`;
        } else {
            connDiv.innerHTML = `
                <span class="status-pulse offline"></span>
                <div class="status-text-group">
                    <span class="status-title">Termux Tidak Terhubung</span>
                    <span class="status-sub" id="statusSub">${data.time_diff && data.time_diff > 60 ? `Sync terakhir ${data.time_diff}s lalu — data akan dihapus otomatis` : 'Jalankan bot.py di Termux untuk sinkronisasi'}</span>
                </div>`;
        }

        // Update toggle auto rejoin
        const toggle = document.getElementById('autoRejoinToggle');
        if (toggle.checked !== data.auto_rejoin) {
            toggle.checked = data.auto_rejoin;
            document.getElementById('autoRejoinStatus').textContent = data.auto_rejoin ? 'ON' : 'OFF';
        }

        // Update setiap kartu akun
        const commands = data.commands || {};
        const statusData = data.status_data || {};
        document.querySelectorAll('.account-card').forEach(card => {
            const pkg = card.getAttribute('data-pkg');
            if (!pkg) return;

            const dot = card.querySelector('.status-dot');
            if (dot && statusData[pkg]) {
                const running = statusData[pkg].running;
                dot.className = 'status-dot ' + (running ? 'online' : 'offline');
            }

            const usernameEl = card.querySelector('.account-username');
if (usernameEl && statusData[pkg] && statusData[pkg].username) {
    const currentUsername = statusData[pkg].username;
    usernameEl.textContent = currentUsername;
    
    // Panggil fungsi avatar di sini
    loadRobloxAvatar(pkg, currentUsername);
    
    const avatarInitial = card.querySelector('.avatar-initial');
    if (avatarInitial) avatarInitial.textContent = currentUsername.charAt(0).toUpperCase();
}


            if (commands[pkg]) {
                const cmd = commands[pkg].cmd || 'IDLE';
                const executed = commands[pkg].executed || false;

                const badgeText = card.querySelector('.cmd-badge-text');
                if (badgeText) badgeText.textContent = cmd;

                const badge = card.querySelector('.cmd-badge');
                if (badge) {
                    let execEl = badge.querySelector('.exec-badge');
                    if (executed && !execEl) {
                        execEl = document.createElement('span');
                        execEl.className = 'exec-badge';
                        execEl.innerHTML = '<i class="fas fa-check"></i> selesai';
                        badge.appendChild(execEl);
                    } else if (!executed && execEl) {
                        execEl.remove();
                    }
                }
            }
        });
    } catch (e) {
        // silent
    }
}

// Polling setiap 3 detik
setInterval(pollDashboard, 3000);
setTimeout(pollDashboard, 500);

// ========== FILE MANAGER (AUTO EXECUTE) ==========
let fileListPolling = null;

function refreshFileList() {
    const container = document.getElementById('fileListContainer');
    container.innerHTML = '<div class="empty-state-sm"><i class="fas fa-spinner fa-spin"></i><p>Mengambil daftar file...</p></div>';
    apiRequest('set_cmd', { pkg: '_file_manager', cmd: 'FILE_LIST', mode: 'public', target: '' })
        .then(res => {
            if (res.status === 'ok') pollFileResult('FILE_LIST');
            else container.innerHTML = '<div class="empty-state-sm"><i class="fas fa-triangle-exclamation"></i><p>Gagal mengirim perintah.</p></div>';
        })
        .catch(err => container.innerHTML = `<div class="empty-state-sm"><i class="fas fa-triangle-exclamation"></i><p>Error: ${err.message}</p></div>`);
}

function pollFileResult(expectedOperation, callback) {
    let attempts = 0;
    const maxAttempts = 15;
    if (fileListPolling) clearInterval(fileListPolling);
    fileListPolling = setInterval(() => {
        attempts++;
        fetch('api.php?action=get_file_result&clear=1')
            .then(res => res.json())
            .then(data => {
                if (data.status === 'ok' && data.result) {
                    const result = data.result;
                    if (result.operation === expectedOperation) {
                        clearInterval(fileListPolling);
                        fileListPolling = null;
                        if (callback) callback(result);
                        else displayFileList(result);
                    }
                }
                if (attempts >= maxAttempts) {
                    clearInterval(fileListPolling);
                    fileListPolling = null;
                    document.getElementById('fileListContainer').innerHTML = '<div class="empty-state-sm"><i class="fas fa-triangle-exclamation"></i><p>Waktu habis, tidak ada respons dari bot.</p></div>';
                }
            })
            .catch(err => { /* ignore */ });
    }, 2000);
}

function displayFileList(result) {
    const container = document.getElementById('fileListContainer');
    if (!result.success) {
        container.innerHTML = `<div class="empty-state-sm"><i class="fas fa-triangle-exclamation"></i><p>${result.message || 'Gagal mengambil daftar'}</p></div>`;
        return;
    }
    const files = result.data || [];
    if (files.length === 0) {
        container.innerHTML = '<div class="empty-state-sm"><i class="fas fa-folder-open"></i><p>Direktori kosong.</p></div>';
        return;
    }
    let html = '';
    files.forEach(file => {
        const size = file.size < 1024 ? file.size + ' B' : (file.size < 1048576 ? (file.size / 1024).toFixed(1) + ' KB' : (file.size / 1048576).toFixed(1) + ' MB');
        const mtime = new Date(file.mtime * 1000).toLocaleString();
        html += `<div class="file-row">
            <div class="file-icon"><i class="fas fa-file-code"></i></div>
            <div class="file-info">
                <div class="file-name">${file.name}</div>
                <div class="file-meta">${size} · ${mtime}</div>
            </div>
            <div class="file-actions">
                <button class="icon-btn-sm" onclick="editFile('${file.name}')" title="Edit"><i class="fas fa-edit"></i></button>
                <button class="icon-btn-sm danger" onclick="deleteFile('${file.name}')" title="Hapus"><i class="fas fa-trash"></i></button>
            </div>
        </div>`;
    });
    container.innerHTML = html;
}

function showAddFileModal() {
    document.getElementById('fileModalTitle').textContent = 'Tambah File';
    document.getElementById('fileOperation').value = 'FILE_ADD';
    document.getElementById('fileName').value = '';
    document.getElementById('fileContent').value = '';
    document.getElementById('fileModal').classList.add('show');
}

function editFile(filename) {
    document.getElementById('fileModalTitle').textContent = 'Edit File: ' + filename;
    document.getElementById('fileOperation').value = 'FILE_EDIT';
    document.getElementById('fileName').value = filename;
    document.getElementById('fileContent').value = '';
    document.getElementById('fileModal').classList.add('show');
}

function closeFileModal() {
    document.getElementById('fileModal').classList.remove('show');
}

async function deleteFile(filename) {
    // Tampilkan konfirmasi SweetAlert
    const confirmResult = await Swal.fire({
        title: 'Delete?',
        text: `You Sure Want Delete "${filename}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff2d55',
        cancelButtonColor: 'rgba(255, 255, 255, 0.1)',
        confirmButtonText: '<i class="fas fa-trash"></i> Yep',
        cancelButtonText: 'Cancel',
        background: 'linear-gradient(135deg, rgba(20,8,14,.98), rgba(12,4,10,.98))',
        color: '#f6eef1',
        backdrop: `rgba(5,2,6,0.7)`
    });

    // Jika tombol batal diklik, hentikan fungsi
    if (!confirmResult.isConfirmed) return;

    apiRequest('set_cmd', { pkg: '_file_manager', cmd: 'FILE_DELETE', target: filename })
        .then(res => {
            if (res.status === 'ok') {
                pollFileResult('FILE_DELETE', function (result) {
                    showToast(result.message || (result.success ? 'File dihapus' : 'Gagal hapus'), result.success ? 'success' : 'error');
                    refreshFileList();
                });
            } else {
                showToast('Gagal mengirim perintah hapus.', 'error');
            }
        });
}

document.getElementById('fileForm').addEventListener('submit', function (e) {
    e.preventDefault();
    const operation = document.getElementById('fileOperation').value;
    const filename = document.getElementById('fileName').value.trim();
    const content = document.getElementById('fileContent').value;
    if (!filename) { showToast('Nama file harus diisi.', 'error'); return; }
    apiRequest('set_cmd', { pkg: '_file_manager', cmd: operation, target: filename, content: content })
        .then(res => {
            if (res.status === 'ok') {
                closeFileModal();
                pollFileResult(operation, function (result) {
                    showToast(result.message || (result.success ? 'Berhasil' : 'Gagal'), result.success ? 'success' : 'error');
                    refreshFileList();
                });
            } else {
                showToast('Gagal mengirim perintah.', 'error');
            }
        });
});

document.getElementById('fileModal').addEventListener('click', function (e) {
    if (e.target === this) closeFileModal();
});

// ========== LOAD ROBLOX AVATAR ==========
const avatarCache = {}; // Cache agar tidak memanggil API berulang kali untuk user yang sama

async function loadRobloxAvatar(pkg, username) {
    if (!username || username === 'Unknown') return;
    
    const imgEl = document.getElementById('avatar-img-' + pkg);
    const initEl = document.getElementById('avatar-initial-' + pkg);
    if (!imgEl || !initEl) return;

    // Jika sudah ada di cache, langsung pakai
    if (avatarCache[username]) {
        imgEl.src = avatarCache[username];
        imgEl.style.display = 'block';
        initEl.style.display = 'none';
        return;
    }

    try {
        const response = await fetch(`api.php?action=get_avatar&username=${username}`);
        const data = await response.json();
        
        if (data.status === 'ok' && data.url) {
            avatarCache[username] = data.url; // Simpan ke cache
            imgEl.src = data.url;
            imgEl.style.display = 'block';
            initEl.style.display = 'none';
        }
    } catch (e) {
        console.error('Gagal memuat avatar untuk ' + username, e);
    }
}

// Jalankan saat halaman pertama kali dimuat
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.account-card').forEach(card => {
        const pkg = card.getAttribute('data-pkg');
        const usernameEl = card.querySelector('.account-username');
        if (usernameEl) {
            loadRobloxAvatar(pkg, usernameEl.textContent.trim());
        }
    });
});

</script>
</body>
</html>
