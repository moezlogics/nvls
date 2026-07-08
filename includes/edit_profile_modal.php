<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$member_id = $_SESSION['member_id'] ?? null;
if (!$member_id) {
    return;
}

// Fetch latest user details to populate the form
global $conn, $site_url;
$stmt = $conn->prepare("SELECT name, bio, profile_pic FROM members WHERE id = ?");
$stmt->bind_param("i", $member_id);
$stmt->execute();
$m_details = $stmt->get_result()->fetch_assoc();
$stmt->close();

$m_name = $m_details['name'] ?? '';
$m_bio = $m_details['bio'] ?? '';
$m_pic = $m_details['profile_pic'] ?? '';

if ($m_pic && !preg_match("~^(?:f|ht)tps?://~i", $m_pic)) {
    $m_pic_url = $site_url . '/' . ltrim($m_pic, '/');
} elseif ($m_pic) {
    $m_pic_url = $m_pic;
} else {
    $m_pic_url = $site_url . '/uploads/community/default_avatar.png';
}
?>

<!-- Edit Profile Modal Overlay -->
<div id="editProfileModal" class="auth-modal-overlay" style="display:none; opacity:0; pointer-events:none; transition: opacity 0.2s ease; position:fixed; inset:0; background:rgba(0,0,0,0.6); z-index:99999; align-items:center; justify-content:center; padding:16px;">
  <div class="auth-modal" style="background:#fff; border-radius:20px; padding:28px; max-width:440px; width:100%; box-shadow:0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04); position:relative; display:flex; flex-direction:column; gap:20px; box-sizing:border-box;">
    
    <!-- Header -->
    <div style="display:flex; justify-content:between; align-items:center; border-bottom:1px solid #f1f5f9; padding-bottom:14px; margin-bottom:4px;">
      <h3 style="font-size:1.15rem; font-weight:800; color:#1e293b; margin:0;">Edit Profile</h3>
      <button onclick="closeEditProfileModal()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#94a3b8; transition:color 0.15s; line-height:1; padding:0; margin-left:auto;" onmouseover="this.style.color='#64748b'" onmouseout="this.style.color='#94a3b8'">×</button>
    </div>

    <!-- Form -->
    <form id="editProfileForm" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:16px; margin:0;">
      
      <!-- Avatar Section -->
      <div style="display:flex; flex-direction:column; align-items:center; gap:8px;">
        <div style="position:relative; width:80px; height:80px;">
          <img id="editProfilePreview" src="<?php echo htmlspecialchars($m_pic_url); ?>" alt="Profile Picture" style="width:80px; height:80px; border-radius:50%; object-fit:cover; border:3px solid var(--theme-secondary); box-shadow:0 4px 6px -1px rgba(0,0,0,0.1);">
          <label for="editProfilePicInput" style="position:absolute; bottom:0; right:0; width:28px; height:28px; border-radius:50%; background:var(--theme-secondary); color:#fff; display:flex; align-items:center; justify-content:center; cursor:pointer; border:2px solid #fff; box-shadow:0 2px 4px rgba(0,0,0,0.15); transition:transform 0.15s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
            <i class="fa-solid fa-camera" style="font-size:0.75rem;"></i>
          </label>
        </div>
        <input type="file" id="editProfilePicInput" name="profile_pic" accept="image/*" style="display:none;" onchange="previewProfilePic(this)">
        <span style="font-size:0.72rem; color:#64748b;">Recommended: Square image, max 2MB</span>
      </div>

      <!-- Display Name -->
      <div style="display:flex; flex-direction:column; gap:6px;">
        <label for="editProfileName" style="font-size:0.8rem; font-weight:700; color:#475569;">Display Name <span style="color:#ef4444;">*</span></label>
        <input type="text" id="editProfileName" name="name" required value="<?php echo htmlspecialchars($m_name); ?>" placeholder="e.g. Ali Khan" style="width:100%; height:42px; padding:0 12px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.9rem; font-family:inherit; outline:none; transition:border-color 0.15s; box-sizing:border-box;" onfocus="this.style.borderColor='var(--theme-secondary)'" onblur="this.style.borderColor='#cbd5e1'">
      </div>

      <!-- Bio -->
      <div style="display:flex; flex-direction:column; gap:6px;">
        <label for="editProfileBio" style="font-size:0.8rem; font-weight:700; color:#475569;">Bio</label>
        <textarea id="editProfileBio" name="bio" rows="3" placeholder="Tell the community about yourself..." style="width:100%; padding:10px 12px; border:1.5px solid #cbd5e1; border-radius:10px; font-size:0.9rem; font-family:inherit; outline:none; resize:vertical; transition:border-color 0.15s; box-sizing:border-box;" onfocus="this.style.borderColor='var(--theme-secondary)'" onblur="this.style.borderColor='#cbd5e1'"><?php echo htmlspecialchars($m_bio); ?></textarea>
      </div>

      <!-- Submit & Action Buttons -->
      <div style="display:flex; gap:12px; margin-top:6px; border-top:1px solid #f1f5f9; padding-top:16px;">
        <button type="button" onclick="closeEditProfileModal()" style="flex:1; height:42px; border:1.5px solid #cbd5e1; border-radius:10px; background:#fff; color:#475569; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit; transition:background-color 0.15s;" onmouseover="this.style.backgroundColor='#f8fafc'" onmouseout="this.style.backgroundColor='#fff'">Cancel</button>
        <button type="submit" id="editProfileSubmitBtn" style="flex:1; height:42px; border:none; border-radius:10px; background:var(--theme-secondary); color:#fff; font-weight:700; font-size:0.9rem; cursor:pointer; font-family:inherit; display:flex; align-items:center; justify-content:center; gap:8px; transition:opacity 0.15s;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
          Save Changes <i class="fa-solid fa-spinner fa-spin" id="editProfileSpinner" style="display:none; font-size:0.85rem;"></i>
        </button>
      </div>

    </form>
  </div>
</div>

<script>
function openEditProfileModal() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.style.display = 'flex';
        // Trigger reflow for opacity transition
        modal.offsetHeight; 
        modal.style.opacity = '1';
        modal.style.pointerEvents = 'all';
    }
}

function closeEditProfileModal() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.style.opacity = '0';
        modal.style.pointerEvents = 'none';
        setTimeout(() => {
            modal.style.display = 'none';
        }, 200);
    }
}

function previewProfilePic(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Basic size validation (2MB)
        if (file.size > 2 * 1024 * 1024) {
            alert('Image size cannot exceed 2MB');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('editProfilePreview');
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('editProfileModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditProfileModal();
            }
        });
    }

    const form = document.getElementById('editProfileForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = document.getElementById('editProfileSubmitBtn');
            const spinner = document.getElementById('editProfileSpinner');
            
            if (submitBtn) submitBtn.disabled = true;
            if (spinner) spinner.style.display = 'inline-block';

            const fd = new FormData(this);
            const actionUrl = '<?php echo $site_url; ?>/community_auth.php?action=update_profile';

            fetch(actionUrl, {
                method: 'POST',
                body: fd
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.error || 'Server error'); });
                }
                return res.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    closeEditProfileModal();
                    // Reload to update dynamic PHP session templates across pages instantly
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to update profile');
                }
            })
            .catch(err => {
                console.error('Profile update error:', err);
                alert(err.message || 'Something went wrong. Please try again.');
            })
            .finally(() => {
                if (submitBtn) submitBtn.disabled = false;
                if (spinner) spinner.style.display = 'none';
            });
        });
    }
});
</script>
