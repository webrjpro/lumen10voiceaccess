# Origem, referências e integridade

O código do VoiceAccess é uma implementação para este projeto. As pesquisas abaixo
orientaram decisões; nenhum código destes repositórios foi incorporado à distribuição.

| Referência primária | Estado consultado | Aplicação |
| --- | --- | --- |
| https://github.com/talonhub/community | 488df87c8abb044805a2d628a874ebd2fd716f92, licença MIT | Comandos por contexto e ajuda acessível |
| https://github.com/david-tejada/rango | fdc339847cf0481d052b926624709d832c6bbc81, licença MIT | Observação de páginas dinâmicas, foco e navegação por controles |
| https://github.com/ggml-org/whisper.cpp/tree/master/examples/stream.wasm | documentação consultada em 16/10/2026 | Estudo de STT local; não integrado nem distribuído |
| https://www.home-assistant.io/integrations/wyoming/ | documentação consultada em 16/10/2026 | Separação entre reconhecimento, intenção e resposta; serviço não integrado |
| https://github.com/dequelabs/axe-core | ferramenta de QA 4.14.0, MPL-2.0 | Auditoria automatizada no navegador; fora dos ZIPs de produção |

O plugin usa APIs do Moodle, Web Speech e os endpoints oficiais dos provedores
configurados. Não inclui modelos, vozes, bibliotecas baixadas ou serviços dos projetos citados.
As dependências de QA permanecem fora dos pacotes.

Pacotes e fontes têm hashes SHA256 e assinatura Ed25519 externa ao ZIP.
A chave pública acompanha os componentes. Guarde uma cópia independente confiável
da chave pública para conferir a procedência de releases futuras.
A assinatura demonstra integridade em relação à chave; não é assinatura ICP-Brasil,
carimbo do tempo de autoridade certificadora nem certificado de registro de software.
Não há ofuscação, bloqueio remoto ou revogação de direitos concedidos pela GPL.
