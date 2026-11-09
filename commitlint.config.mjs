/** Conventional Commits, e.g. `feat(api): reserve quota lease`. */
export default {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'scope-enum': [2, 'always', ['api', 'web', 'db', 'docker', 'ci', 'docs', 'ai', 'deps', 'repo']],
    'body-max-line-length': [1, 'always', 100],
  },
};
