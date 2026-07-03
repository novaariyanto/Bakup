import './bootstrap';
import './backup-progress-poll';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerBackupProfileForm } from './backup-profile-form';

Alpine.plugin(focus);
registerBackupProfileForm(Alpine);
window.Alpine = Alpine;
Alpine.start();
