import { config } from './config.js';

// Default SVG icons for avatars.
const DEFAULT_USER_AVATAR_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';

const DEFAULT_BOT_AVATAR_SVG =
  '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"></path><rect width="16" height="12" x="4" y="8" rx="2"></rect><path d="M2 14h2"></path><path d="M20 14h2"></path><path d="M15 13v2"></path><path d="M9 13v2"></path></svg>';

/**
 * Set avatar content for a message avatar element.
 *
 * @param {HTMLElement} avatarDiv - The avatar container element.
 * @param {string} type - 'user' or 'bot'.
 */
export function setAvatarContent(avatarDiv, type) {
  const avatarUrl = type === 'user' ? config.userAvatarUrl : config.botAvatarUrl;

  if (avatarUrl && avatarUrl.length > 0) {
    // Custom image avatar
    const img = document.createElement('img');
    img.src = avatarUrl;
    img.alt = type === 'user' ? 'User' : 'Bot';
    img.className = 'humata-avatar-img';
    avatarDiv.innerHTML = '';
    avatarDiv.appendChild(img);
  } else {
    // Default SVG icon
    avatarDiv.innerHTML = type === 'user' ? DEFAULT_USER_AVATAR_SVG : DEFAULT_BOT_AVATAR_SVG;
  }
}


