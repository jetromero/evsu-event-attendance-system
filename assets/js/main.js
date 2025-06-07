/*=============== SHOW HIDE PASSWORD LOGIN ===============*/
const passwordAccess = (loginPass, loginEye) =>{
   const input = document.getElementById(loginPass),
         iconEye = document.getElementById(loginEye)

   iconEye.addEventListener('click', () =>{
      // Change password to text
      input.type === 'password' ? input.type = 'text'
						              : input.type = 'password'

      // Icon change
      iconEye.classList.toggle('ri-eye-fill')
      iconEye.classList.toggle('ri-eye-off-fill')
   })
}
passwordAccess('password','loginPassword')

/*=============== SHOW HIDE PASSWORD CREATE ACCOUNT ===============*/
const passwordRegister = (loginPass, loginEye) =>{
   const input = document.getElementById(loginPass),
         iconEye = document.getElementById(loginEye)

   iconEye.addEventListener('click', () =>{
      // Change password to text
      input.type === 'password' ? input.type = 'text'
						              : input.type = 'password'

      // Icon change
      iconEye.classList.toggle('ri-eye-fill')
      iconEye.classList.toggle('ri-eye-off-fill')
   })
}
passwordRegister('passwordCreate','loginPasswordCreate')

/*=============== SHOW HIDE LOGIN & CREATE ACCOUNT ===============*/
const loginAcessRegister = document.getElementById('loginAccessRegister'),
      buttonRegister = document.getElementById('loginButtonRegister'),
      buttonAccess = document.getElementById('loginButtonAccess')

buttonRegister.addEventListener('click', () => {
   // Clear any existing notifications when switching to register
   clearNotifications()
   loginAcessRegister.classList.add('active')
})

buttonAccess.addEventListener('click', () => {
   // Check if there are error notifications on the register page
   const registerNotifications = document.getElementById('registerNotifications')
   const hasErrors = registerNotifications && registerNotifications.querySelector('.notification--error')
   
   // If there are error notifications, don't switch to login page
   if (hasErrors) {
      console.log('Error notifications present - staying on register page')
      return false
   }
   
   // Clear any existing notifications when switching to login
   clearNotifications()
   loginAcessRegister.classList.remove('active')
})

/*=============== SELECT LABEL ANIMATION ===============*/
const handleSelectLabel = () => {
   const selects = document.querySelectorAll('.login__select')

   selects.forEach(select => {
      const label = select.nextElementSibling

      // Function to update label position
      const updateLabel = () => {
         if (select.value && select.value !== '') {
            label.style.transform = 'translateY(-12px)'
            label.style.fontSize = 'var(--tiny-font-size)'
         } else {
            label.style.transform = ''
            label.style.fontSize = ''
         }
      }

      // Update on change
      select.addEventListener('change', updateLabel)

      // Update on focus
      select.addEventListener('focus', () => {
         label.style.transform = 'translateY(-12px)'
         label.style.fontSize = 'var(--tiny-font-size)'
      })

      // Update on blur
      select.addEventListener('blur', updateLabel)

      // Initial update
      updateLabel()
   })
}

// Initialize select label handling
handleSelectLabel()

/*=============== NOTIFICATION SYSTEM ===============*/
// Function to close notification manually
function closeNotification(button) {
   console.log('closeNotification called with:', button); // Debug log

   // Find the notification element
   let notification = null;
   if (button && button.closest) {
      notification = button.closest('.notification');
   } else if (button && button.parentNode) {
      // Fallback: traverse up manually
      let element = button;
      while (element && element.parentNode) {
         if (element.classList && element.classList.contains('notification')) {
            notification = element;
            break;
         }
         element = element.parentNode;
      }
   }

   if (notification) {
      console.log('Notification found, closing...'); // Debug log

      // Apply hiding animation
      notification.style.transition = 'all 0.3s ease';
      notification.style.transform = 'translateY(-100%)';
      notification.style.opacity = '0';
      notification.style.maxHeight = '0';
      notification.style.marginBottom = '0';
      notification.style.paddingTop = '0';
      notification.style.paddingBottom = '0';

      // Remove after animation
      setTimeout(() => {
         if (notification && notification.parentNode) {
            notification.remove();
            console.log('Notification removed'); // Debug log
         }
      }, 300);
   } else {
      console.log('No notification found for button:', button); // Debug log
   }
}

// Make function globally accessible
window.closeNotification = closeNotification;

// Set up global event delegation for close buttons (immediate execution)
document.addEventListener('click', function(e) {
   console.log('Document click detected on:', e.target);
   if (e.target && e.target.classList && e.target.classList.contains('notification__close')) {
      console.log('Close button clicked via global event delegation');
      e.preventDefault();
      e.stopPropagation();
      closeNotification(e.target);
   }
}, true); // Use capture phase to ensure it works

// Function to initialize notifications (without auto-hide)
function initializeNotifications() {
   console.log('initializeNotifications called'); // Debug log
   const notifications = document.querySelectorAll('.notification');
   console.log('Found notifications:', notifications.length); // Debug log

   notifications.forEach((notification, index) => {
      console.log(`Initializing notification ${index + 1}`); // Debug log
      // Mark notification as initialized
      notification.setAttribute('data-initialized', 'true');
   });
}

// Function to clear notifications when switching forms
function clearNotifications() {
   const allNotifications = document.querySelectorAll('.notification');
   allNotifications.forEach(notification => {
      notification.style.transition = 'all 0.3s ease';
      notification.style.transform = 'translateY(-100%)';
      notification.style.opacity = '0';
      notification.style.maxHeight = '0';
      notification.style.marginBottom = '0';
      notification.style.paddingTop = '0';
      notification.style.paddingBottom = '0';

      setTimeout(() => {
         if (notification.parentNode) {
            notification.remove();
         }
      }, 300);
   });
}

// Function to show notification programmatically
function showNotification(message, type = 'info', container = 'loginNotifications') {
   const notificationContainer = document.getElementById(container);
   if (!notificationContainer) return;

   const notification = document.createElement('div');
   notification.className = `notification notification--${type}`;

   notification.innerHTML = `
      <span class="notification__message">${message}</span>
      <button class="notification__close" onclick="closeNotification(this)">&times;</button>
   `;

   notificationContainer.appendChild(notification);

   // Mark as initialized
   notification.setAttribute('data-initialized', 'true');
}

// Initialize notification system
document.addEventListener('DOMContentLoaded', function() {
   console.log('DOM Content Loaded - Initializing notifications'); // Debug log

   // Initialize existing notifications (without auto-hide)
   initializeNotifications();
   
   // Initialize email validation
   initEmailValidation();
   
   // Handle form state based on errors - ONLY if there are registration errors AND it was a registration attempt
   handleFormState();

   // Check URL hash to switch to register form
   if (window.location.hash === '#register') {
      const loginAccessRegister = document.getElementById('loginAccessRegister');
      if (loginAccessRegister) {
         loginAccessRegister.classList.add('active');
      }
   }
});

// Immediate check for registration errors (runs as soon as script loads)
(function() {
   console.log('Immediate registration error check');
   
   function checkForRegistrationErrors() {
      // Only check for registration errors if this was actually a registration attempt
      // Check if the page has registration form data (indicates a registration attempt)
      const registerForm = document.querySelector('form[action*="register_submit"], input[name="register_submit"]');
      const hasRegisterData = document.querySelector('input[name="names"]')?.value || 
                             document.querySelector('input[name="surnames"]')?.value ||
                             document.querySelector('input[name="emailCreate"]')?.value ||
                             document.querySelector('input[name="course"]')?.value ||
                             document.querySelector('select[name="year_level"]')?.value ||
                             document.querySelector('select[name="section"]')?.value;
      
      // Check if there was a PHP registration error by looking for the registration error message
      const hasRegistrationError = document.querySelector('#registerNotifications .notification--error');
      
      // Only switch to registration form if there was actually a registration attempt with errors
      if (hasRegistrationError && hasRegisterData) {
         console.log('Immediate check: Registration errors found after registration attempt, switching to registration form');
         const loginAccessRegister = document.getElementById('loginAccessRegister');
         if (loginAccessRegister) {
            loginAccessRegister.classList.add('active');
            console.log('Immediate check: Successfully switched to registration form');
         }
      } else {
         console.log('Immediate check: No registration errors or no registration attempt detected');
      }
   }
   
   // Run immediately if DOM is ready
   if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', checkForRegistrationErrors);
   } else {
      checkForRegistrationErrors();
   }
   
   // Also run after a short delay as backup
   setTimeout(checkForRegistrationErrors, 100);
})();

// Function to check for registration errors and show appropriate form
function handleFormState() {
   console.log('handleFormState called');
   
   // Only check for registration errors if this was actually a registration attempt
   const hasRegisterData = document.querySelector('input[name="names"]')?.value || 
                          document.querySelector('input[name="surnames"]')?.value ||
                          document.querySelector('input[name="emailCreate"]')?.value ||
                          document.querySelector('input[name="course"]')?.value ||
                          document.querySelector('select[name="year_level"]')?.value ||
                          document.querySelector('select[name="section"]')?.value;
   
   const registerNotifications = document.getElementById('registerNotifications');
   console.log('registerNotifications found:', !!registerNotifications);
   
   if (registerNotifications && hasRegisterData) {
      const errorNotifications = registerNotifications.querySelectorAll('.notification--error');
      console.log('Error notifications found:', errorNotifications.length);
      
      if (errorNotifications.length > 0) {
         console.log('Registration errors detected after registration attempt - switching to registration form');
         const loginAcessRegister = document.getElementById('loginAccessRegister');
         if (loginAcessRegister) {
            loginAcessRegister.classList.add('active');
            console.log('Successfully switched to registration form');
         } else {
            console.log('loginAccessRegister element not found');
         }
      } else {
         console.log('No registration errors found');
      }
   } else {
      console.log('No registration attempt detected or registerNotifications container not found');
   }
}

// Email validation function for EVSU domain
function validateEVSUEmail(email) {
    const evsuPattern = /^[a-zA-Z0-9._%+-]+@evsu\.edu\.ph$/;
    return evsuPattern.test(email);
}

// Add email validation to login and register forms
function initEmailValidation() {
    const loginEmail = document.getElementById('email');
    const registerEmail = document.getElementById('emailCreate');
    
    if (loginEmail) {
        loginEmail.addEventListener('blur', function() {
            if (this.value && !validateEVSUEmail(this.value)) {
                showNotification('Please use a valid EVSU email address (@evsu.edu.ph)', 'error', 'loginNotifications');
            }
        });
    }
    
    if (registerEmail) {
        registerEmail.addEventListener('blur', function() {
            if (this.value && !validateEVSUEmail(this.value)) {
                showNotification('Please use a valid EVSU email address (@evsu.edu.ph)', 'error', 'registerNotifications');
            }
        });
    }
}
