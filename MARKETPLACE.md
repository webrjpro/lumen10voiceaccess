# Cadastro dos dois componentes no Moodle Marketplace

Cadastre cada componente usando seu ZIP de `packages/`, nunca o ZIP geral deste
repositório. Para revisão, os avaliadores precisam conseguir acessar os fontes e
o rastreador de problemas. O envio ao GitHub não altera a visibilidade do repositório.

## 1. Plugin principal

| Campo | Valor |
| --- | --- |
| ZIP | `local_lumen10_voiceaccess-0.5.1-beta.zip` |
| Tipo | Local plugin / Plugin local |
| Componente | `local_lumen10_voiceaccess` |
| Nome | Lumen10 Acesso Universal |
| Versão numérica | `2026110300` |
| Lançamento | `0.5.1-beta` |
| Maturidade | Beta |
| Moodle | 5.1 e 5.2 |
| Repositório Git | `https://github.com/webrjpro/lumen10voiceaccess` |
| Ramificação | `main` |
| Tag | `v0.5.1-beta` |
| Problemas | `https://github.com/webrjpro/lumen10voiceaccess/issues` |
| Documentação | `https://github.com/webrjpro/lumen10voiceaccess/blob/main/local/lumen10_voiceaccess/INSTALLATION.md` |

Descrição em inglês:

> Lumen10 Acesso Universal provides optional voice and keyboard accessibility assistance
> for Moodle 5.1 and 5.2. It includes personal accessibility preferences, user approval,
> page reading, navigation assistance and a simplified learning interface.
>
> Optional institutional Google Gemini Live conversation uses the institution's own API
> key, kept server-side. New installations contain no production credentials and have AI
> disabled by default. Administrators configure their own provider access, models,
> audiences, quotas and participant consent. Provider usage charges are not included.
>
> An alternative personal OpenAI or Anthropic integration can interpret navigation
> commands when allowed by the institution. External processing may include microphone
> audio, command text and limited navigation or requested page-reading context, depending
> on the enabled feature. Browser speech services may also use external processing.
>
> The companion mod_lumen10voiceaccess activity is optional and provides a course entry
> point. Install this local plugin first. This is a beta release and does not claim
> universal device support or complete accessibility compliance. GNU GPL v3 or later.

Notas de lançamento:

> Distribution revision based on the inspected 0.5.0-beta production runtime.
> Adds customer-owned API key installation instructions, external-processing disclosure
> and explicit Moodle 5.1/5.2 compatibility metadata. No real credentials are included.
> Runtime logic is unchanged. Remains beta.

## 2. Atividade opcional

| Campo | Valor |
| --- | --- |
| ZIP | `mod_lumen10voiceaccess-0.2.1-beta.zip` |
| Tipo | Activity module / Módulo de atividade |
| Componente | `mod_lumen10voiceaccess` |
| Nome | Lumen10 Acesso Universal - Course activity |
| Versão numérica | `2026110300` |
| Lançamento | `0.2.1-beta` |
| Maturidade | Beta |
| Moodle | 5.1 e 5.2 |
| Repositório Git | `https://github.com/webrjpro/lumen10voiceaccess` |
| Ramificação | `main` |
| Tag | `mod-v0.2.1-beta` |
| Problemas | `https://github.com/webrjpro/lumen10voiceaccess/issues` |
| Dependência | `local_lumen10_voiceaccess` versão `2026101802` ou superior |

Descrição em inglês:

> Optional course activity providing instructions and a link to the site-wide Lumen10
> VoiceAccess preferences. Requires local_lumen10_voiceaccess version 2026101802 or later,
> installed separately. The voice assistant and AI integrations are supplied by that
> companion local plugin, not by this activity alone.
>
> Supports Moodle 5.1 and 5.2, native view-based completion and backup/restore of the
> activity and its introduction files. Personal settings, AI keys and audio are not
> included in course backups. Optional AI uses the customer's own credentials and may
> incur separate provider charges. Beta release; GNU GPL v3 or later.

Notas de lançamento:

> Updates installation and external-service documentation, corrects the companion
> dependency requirement and declares Moodle 5.1/5.2 support. Activity runtime logic
> is unchanged from 0.2.0-beta. No real credentials are included.

## Capturas e preço

Use capturas com dados fictícios: painel de preferências, interface do assistente,
percurso simplificado e gestão de autorização sem nomes reais. Nunca mostre chaves,
tokens ou dados de alunos. A sugestão comercial é oferecer a beta gratuitamente e
contratar instalação, treinamento e suporte separadamente; a escolha final do preço
pertence ao responsável pela publicação.
