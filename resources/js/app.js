import './bootstrap';
import './backup-profile-form';
import './backup-progress-poll';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';

Alpine.plugin(focus);
window.Alpine = Alpine;
Alpine.start();
