import type { PlaywrightTestConfig, Project } from '@playwright/test';
import base from './playwright.config';

function withBundledChromium(project: Project): Project {
  const { channel: _chromeChannel, ...use } = project.use ?? {};
  return { ...project, use };
}

export default { ...base, projects: base.projects?.map(withBundledChromium) } satisfies PlaywrightTestConfig;
