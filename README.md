<p align="center">
  <img src="logo.png" alt="SMS Vert Pro" width="220" />
</p>

# SMS Vert Pro — Serveur MCP PHP

Serveur [MCP (Model Context Protocol)](https://modelcontextprotocol.io) en **PHP** pour envoyer des SMS professionnels via [SMS Vert Pro](https://www.smsvertpro.com) depuis n'importe quel agent IA : Claude, ChatGPT, LangChain, CrewAI, AutoGen, etc.

> Version Python disponible : [mcp-server-smsvertpro-python](https://github.com/3-bees-online/mcp-server-smsvertpro-python)

## Prérequis

1. **PHP 7.4+** avec l'extension `curl` activée
2. **Un compte SMS Vert Pro** — [inscription gratuite](https://www.smsvertpro.com/espace-client/?type=1) (10 SMS offerts)
3. **Un token API Bearer** — générez-le depuis l'API V2 de votre compte

## Installation

```bash
git clone https://github.com/3-bees-online/mcp-server-smsvertpro-php.git
cd mcp-server-smsvertpro
```

Aucune dépendance externe. Un seul fichier PHP.

## Configuration

Définissez votre token API en variable d'environnement :

```bash
export SMSVERTPRO_API_TOKEN="votre_token_api_ici"
```

### Claude Desktop

Ajoutez dans votre fichier `claude_desktop_config.json` :

```json
{
  "mcpServers": {
    "smsvertpro": {
      "command": "php",
      "args": ["/chemin/vers/server.php"],
      "env": {
        "SMSVERTPRO_API_TOKEN": "votre_token_api_ici"
      }
    }
  }
}
```

### Claude Code

```bash
claude mcp add smsvertpro -- php /chemin/vers/server.php
```

Puis configurez la variable d'environnement `SMSVERTPRO_API_TOKEN`.

## Outils disponibles

| Outil | Description |
|---|---|
| `send_sms` | Envoyer un SMS (immédiat ou programmé) |
| `check_credits` | Consulter le solde de crédits |
| `get_delivery_report` | Rapport de délivrabilité d'une campagne |
| `get_responses` | Réponses SMS reçues (bidirectionnel) |
| `verify_number` | Vérifier le format d'un numéro (syntaxe) |
| `get_blacklist` | Liste des désabonnements (STOP) |
| `generate_otp` | Envoyer un code OTP par SMS |
| `verify_otp` | Vérifier un code OTP |
| `cancel_sms` | Annuler un SMS programmé ou une campagne |

## Exemples d'utilisation

Une fois le serveur MCP connecté, demandez simplement à votre agent IA :

- *"Envoie un SMS au 33612345678 pour annoncer notre promo de printemps -20%"*
- *"Combien de crédits SMS il me reste ?"*
- *"Vérifie si le SMS de la campagne 12345 a été délivré"*
- *"Envoie un code OTP au 33698765432 pour confirmer l'inscription"*

L'agent IA utilisera automatiquement les bons outils avec les bons paramètres.

## Format des numéros

Les numéros de téléphone doivent être au **format international sans le `+`** :
- France : `33612345678` (pas `0612345678`, pas `+33612345678`)
- Belgique : `32470123456`
- Suisse : `41791234567`

## Sécurité

- Votre token API reste sur votre machine, il n'est jamais partagé avec l'agent IA
- Le serveur MCP communique uniquement avec `https://www.smsvertpro.com/api/v2/`
- Pour les SMS marketing (promotions, publicité), l'expéditeur doit ajouter `STOP 36173` à la fin du message (obligation légale). Cette mention n'est pas ajoutée automatiquement par l'API
- La liste noire (désabonnements) est respectée automatiquement par la plateforme

## Liens

- [SMS Vert Pro](https://www.smsvertpro.com)
- [Documentation API V2](https://www.smsvertpro.com/api-smsvertpro/)
- [Intégration IA](https://www.smsvertpro.com/integration-ia/)
- [Tarifs SMS](https://www.smsvertpro.com/tarifs/)

## Licence

MIT — Voir [LICENSE](LICENSE)
