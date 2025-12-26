/**
 * Bot Protection Module
 *
 * Client-side implementation of honeypot and proof-of-work protection.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

import { config } from './config.js';

// Session storage key for PoW verification
const POW_VERIFIED_KEY = 'humata_pow_verified';

// Track page load timestamp for honeypot timing check
let pageLoadTimestamp = 0;

// Track if PoW has been solved in this session
let powSolved = false;

// Store the last PoW nonce/solution for reuse
let lastPowNonce = null;
let lastPowSolution = null;

/**
 * Initialize bot protection.
 * Should be called on page load.
 */
export function initBotProtection() {
    // Get page load time from server or use current time
    pageLoadTimestamp = config.pageLoadTime || Math.floor(Date.now() / 1000);
    
    // Check if already verified in this session
    powSolved = sessionStorage.getItem(POW_VERIFIED_KEY) === 'true';
}

/**
 * Check if bot protection is enabled.
 *
 * @returns {boolean}
 */
export function isBotProtectionEnabled() {
    return config.botProtection && config.botProtection.enabled;
}

/**
 * Check if honeypot protection is enabled.
 *
 * @returns {boolean}
 */
export function isHoneypotEnabled() {
    return isBotProtectionEnabled() && config.botProtection.honeypotEnabled;
}

/**
 * Check if proof-of-work is enabled.
 *
 * @returns {boolean}
 */
export function isPowEnabled() {
    return isBotProtectionEnabled() && config.botProtection.powEnabled;
}

/**
 * Check if PoW has been solved in this session.
 *
 * @returns {boolean}
 */
export function isPowVerified() {
    return powSolved || sessionStorage.getItem(POW_VERIFIED_KEY) === 'true';
}

/**
 * Mark PoW as verified for this session.
 */
export function setPowVerified() {
    powSolved = true;
    sessionStorage.setItem(POW_VERIFIED_KEY, 'true');
}

/**
 * Get the honeypot headers for a request.
 * Returns empty honeypot value (bots would fill this) and timestamp.
 *
 * @returns {Object} Headers object with honeypot data.
 */
export function getHoneypotHeaders() {
    if (!isHoneypotEnabled()) {
        return {};
    }

    return {
        'X-Humata-HP': '',  // Honeypot field - must be empty for legitimate users
        'X-Humata-TS': String(pageLoadTimestamp)  // Timestamp for timing check
    };
}

/**
 * Solve a proof-of-work challenge.
 * Finds a nonce that produces a SHA256 hash with the required number of leading zeros.
 *
 * @param {string} challenge The challenge nonce from the server.
 * @param {number} difficulty Number of leading zeros required.
 * @returns {Promise<string>} The solution (counter value).
 */
export async function solveProofOfWork(challenge, difficulty) {
    const target = '0'.repeat(difficulty);
    let counter = 0;
    const maxIterations = 10000000; // Safety limit
    
    // Use Web Crypto API for SHA256
    const encoder = new TextEncoder();
    
    while (counter < maxIterations) {
        const input = challenge + ':' + counter;
        const data = encoder.encode(input);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
        
        if (hashHex.startsWith(target)) {
            return String(counter);
        }
        
        counter++;
        
        // Yield to the event loop periodically to keep UI responsive
        if (counter % 10000 === 0) {
            await new Promise(resolve => setTimeout(resolve, 0));
        }
    }
    
    throw new Error('Failed to solve proof-of-work challenge');
}

/**
 * Get the PoW headers for a request.
 * If PoW is required and not yet solved, this will solve it first.
 *
 * @param {Object} challengeData Optional challenge data from a previous 403 response.
 * @returns {Promise<Object>} Headers object with PoW data.
 */
export async function getPowHeaders(challengeData = null) {
    if (!isPowEnabled()) {
        return {};
    }

    // If already verified, return empty headers (server will accept based on transient)
    if (isPowVerified() && !challengeData) {
        return {};
    }

    // If we have a cached solution and no new challenge, reuse it
    if (lastPowNonce && lastPowSolution && !challengeData) {
        return {
            'X-Humata-PoW-Nonce': lastPowNonce,
            'X-Humata-PoW-Solution': lastPowSolution
        };
    }

    // If we have challenge data from a 403 response, solve it
    if (challengeData && challengeData.nonce && challengeData.difficulty) {
        const solution = await solveProofOfWork(challengeData.nonce, challengeData.difficulty);
        lastPowNonce = challengeData.nonce;
        lastPowSolution = solution;
        
        return {
            'X-Humata-PoW-Nonce': challengeData.nonce,
            'X-Humata-PoW-Solution': solution
        };
    }

    // No challenge data and not verified - request will fail with 403 and challenge
    return {};
}

/**
 * Get all bot protection headers for a request.
 *
 * @param {Object} powChallenge Optional PoW challenge data from a previous 403 response.
 * @returns {Promise<Object>} Combined headers object.
 */
export async function getBotProtectionHeaders(powChallenge = null) {
    if (!isBotProtectionEnabled()) {
        return {};
    }

    const honeypotHeaders = getHoneypotHeaders();
    const powHeaders = await getPowHeaders(powChallenge);

    return {
        ...honeypotHeaders,
        ...powHeaders
    };
}

/**
 * Handle a 403 response that may contain a PoW challenge.
 * Extracts challenge data from the error response.
 *
 * @param {Object} errorData The error data from the response.
 * @returns {Object|null} Challenge data or null if not a PoW challenge.
 */
export function extractPowChallenge(errorData) {
    if (!errorData) {
        return null;
    }

    // Check for pow_required or pow_expired error codes
    if (errorData.code === 'pow_required' || errorData.code === 'pow_expired' || errorData.code === 'pow_invalid') {
        if (errorData.data && errorData.data.challenge) {
            return errorData.data.challenge;
        }
    }

    return null;
}

/**
 * Clear PoW verification state (e.g., when session expires).
 */
export function clearPowVerification() {
    powSolved = false;
    lastPowNonce = null;
    lastPowSolution = null;
    sessionStorage.removeItem(POW_VERIFIED_KEY);
}
