# Declaração técnica de integridade

Titular indicado nos avisos autorais: **Carlos Antonio de Oliveira Piquet**.
Componentes: `local_lumen10_voiceaccess` e `mod_lumen10voiceaccess`.
Licença: GNU GPL versão 3 ou posterior. Distribuição de 03/11/2026.

`packages/manifest.json` contém hashes SHA-256 dos fontes e dos dois instaladores.
`packages/manifest.signature.json` contém a assinatura Ed25519 dos bytes desse
manifesto. `packages/RELEASE_PUBLIC_KEY.pem` é a chave pública. A chave privada
permanece fora deste repositório e dos ZIPs.

Para conferir: valide a assinatura do manifesto com uma cópia confiável da chave
pública; depois compare os hashes indicados com os arquivos recebidos. Uma cópia
independente confiável da chave pública é necessária para evitar confiar apenas em
uma chave substituída junto com os arquivos. O manifesto cobre os fontes dos dois
componentes e os ZIPs; não assina o histórico Git inteiro nem todos os documentos
administrativos deste repositório.

Isso detecta divergências dos arquivos cobertos em relação à chave técnica. Não
comprova identidade civil de forma independente, não impede cópias ou modificações
e não equivale a registro no INPI, certificação ICP-Brasil ou ausência de vulnerabilidades.
Alterar um arquivo coberto exige emitir um novo manifesto e uma nova assinatura.

A GPL permite copiar, modificar e redistribuir conforme suas condições. Os avisos
de autoria e licença devem ser respeitados. A assinatura não restringe esses direitos.
Para registrar uma versão no INPI, siga o processo oficial e preserve exatamente
o arquivo utilizado para gerar o hash do depósito:
https://www.gov.br/inpi/pt-br/assuntos/programas-de-computador
