import { test, expect } from '@playwright/test';
import { baseUrl } from '@config/core/urls';
import {
  accounting,
  admin,
  adminViewOnly,
  configuration,
  customerService,
  participantView,
  platformAdmin,
  reporting,
  superAdmin,
} from '@config/auth/credentials';
import { lockoutGuard } from '@utils/auth/lockout-guard';

const roleLogins = [
  ['Super Admin', superAdmin],
  ['Organization Admin', admin],
  ['Reporting', reporting],
  ['Accounting', accounting],
  ['Configuration', configuration],
  ['Participant View', participantView],
  ['Customer Service', customerService],
  ['Admin View Only', adminViewOnly],
  ['Platform Admin holder', platformAdmin],
] as const;

function basicAuth(username: string, password: string): string {
  return `Basic ${Buffer.from(`${username}:${password}`).toString('base64')}`;
}

test('every admin role signs in to QA once, stopping at the first refusal', async () => {
  expect(new URL(baseUrl).hostname.endsWith('.adrqa.info'), `BASE_URL ${baseUrl} is not a QA host`).toBe(true);

  for (const [role, login] of roleLogins) {
    const guard = lockoutGuard.canAttempt('token');
    expect(guard.safe, guard.reason).toBe(true);

    const response = await fetch(`${baseUrl.replace(/\/$/, '')}/token`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: basicAuth(login.username, login.password) },
      body: '{}',
    });
    const isSignedIn = response.status >= 200 && response.status < 300;
    if (isSignedIn || [401, 403, 423].includes(response.status)) {
      lockoutGuard.record('token', isSignedIn);
    }
    if (response.status === 423) {
      lockoutGuard.markLocked('token');
    }

    expect(isSignedIn, `${role} sign-in returned HTTP ${response.status}; stopping before any other login`).toBe(true);
  }
});
