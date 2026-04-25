<?php
function getPlatformIcon($platformName) {
    $platformName = strtolower(trim($platformName));
    
    switch ($platformName) {
        case 'netflix':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#E50914" width="40" height="40"><path d="M6 2v20h3.5v-11l5.5 11h3V2h-3.5v11L9 2H6z"/></svg>';
            
        case 'amazon prime':
        case 'amazon':
        case 'prime':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#00A8E1" width="40" height="40"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/></svg>';
            
        case 'hotstar':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1F8B24" width="40" height="40"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>';
            
        case 'hbo max':
        case 'max':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#B535F6" width="40" height="40"><path d="M4 4h4v6h8V4h4v16h-4v-6H8v6H4V4z"/></svg>';
            
        case 'spotify':
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="#1DB954" width="40" height="40"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.6 14.6c-.2.3-.6.4-.9.2-2.4-1.5-5.4-1.8-8.9-1-.3.1-.7-.1-.8-.4-.1-.3.1-.7.4-.8 3.8-.9 7.1-.5 9.8 1.1.3.2.4.6.4.9zm1.3-2.9c-.2.4-.7.5-1.1.3-2.8-1.7-7.1-2.2-10.4-1.2-.4.1-.9-.1-1-.5-.1-.4.1-.9.5-1 3.8-1.2 8.6-.6 11.8 1.4.3.2.4.7.2 1zm.1-3C14.7 9 8.5 8.8 5 9.9c-.5.1-1-.2-1.2-.7-.1-.5.2-1 .7-1.2 4-1.3 10.8-1 14.6 1.3.5.3.6.9.3 1.3-.3.5-.9.6-1.4.4z"/></svg>';
            
        default:
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="40" height="40"><path d="M21 3H3c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h18c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H3V5h18v14zM8 15c0-1.66 1.34-3 3-3 .35 0 .69.07 1 .18V6h5v2h-3v7.03c-.02 1.64-1.35 2.97-3 2.97-1.66 0-3-1.34-3-3z"/></svg>';
    }
}
?>
