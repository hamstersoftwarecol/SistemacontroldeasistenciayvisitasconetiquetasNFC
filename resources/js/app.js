import Alpine from 'alpinejs';

import aiChat from './components/ai-chat';
import chat from './components/chat';
import dashboardLive from './components/dashboard-live';
import kiosk from './components/kiosk';
import nfcScanner from './components/nfc-scanner';
import nfcWriter from './components/nfc-writer';
import teamBoard from './components/team-board';

window.Alpine = Alpine;

Alpine.data('aiChat', aiChat);
Alpine.data('chat', chat);
Alpine.data('dashboardLive', dashboardLive);
Alpine.data('kiosk', kiosk);
Alpine.data('nfcScanner', nfcScanner);
Alpine.data('nfcWriter', nfcWriter);
Alpine.data('teamBoard', teamBoard);

Alpine.start();
