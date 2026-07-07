import './bootstrap';
import './backup-progress-poll';
import './mydumper-progress-poll';
import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import { registerBackupProfileForm } from './backup-profile-form';
import { registerMydumperExportForm } from './mydumper-export-form';

Alpine.plugin(focus);
registerBackupProfileForm(Alpine);
registerMydumperExportForm(Alpine);
window.Alpine = Alpine;
Alpine.start();
