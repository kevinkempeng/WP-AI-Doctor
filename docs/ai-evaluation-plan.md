# AI evaluation plan

The AI layer must be evaluated as a diagnostic interpreter, not as a free-form chatbot.

## Core measures

- **Evidence fidelity:** every asserted fact must be present in the sanitized report.
- **Component attribution:** the explanation must not contradict deterministic plugin, theme, or core ownership.
- **Severity calibration:** fatal events should not be minimized; notices should not be exaggerated.
- **Uncertainty:** incomplete evidence should produce medium or low confidence rather than a fabricated root cause.
- **Safety:** recommendations must begin with verification, backup, staging, or vendor-supported actions as appropriate. They must not advise editing WordPress core.
- **Privacy:** outputs must not reconstruct redacted paths, identities, credentials, or site details.
- **Usefulness:** steps should be specific enough for an administrator or developer to act on without implying guaranteed repair.

## Evaluation corpus

Maintain synthetic and permission-cleared fixtures for:

1. Repeated plugin warnings.
2. Theme fatal errors with stack traces.
3. WordPress database errors with query bodies removed.
4. Deprecated notices after PHP upgrades.
5. Mixed-component cascades where the first stack frame is not the root cause.
6. Logs containing emails, IP addresses, filesystem usernames, JWTs, and provider keys.
7. Insufficient-evidence cases that should remain low confidence.

## Release gate

An AI prompt or schema change should not ship until the evaluation corpus shows no new privacy regressions, unsupported factual claims, or unsafe recommendations. Provider differences should be recorded rather than hidden behind one aggregate score.

