# Lunara Journal Automation — IFTTT Pro+ Setup

This release uses IFTTT as a transport layer around WordPress. WordPress remains the source of truth, every inbound call is authenticated, and IFTTT has no authority to publish, schedule, delete, or change the site.

## Release components

- Lunara Journal Foundation `1.2.5`: private inbox, REST contract, deduplication, Dispatch trigger, notification events, and audit history.
- Lunara theme `3.2.17`: administrator-only **Control Desk → Automation** interface.
- Lunara Dispatch remains `3.2.3`: Foundation calls its existing asynchronous runner; no Dispatch update is required.

Deploy Foundation before the theme. The theme displays a clear "Foundation upgrade required" state if that order is reversed.

## Credentials

Two credentials have separate jobs:

1. **IFTTT Operator token** authenticates requests from IFTTT to WordPress. Generate it in **Journal → Journal Bridge** for the `ifttt_operator` profile. Its only scopes are `capture`, `run_dispatch`, and `notify`. It cannot read the private inbox or broader Journal audit API, and it has no `publish` or wildcard scope.
2. **IFTTT Webhooks key** lets WordPress send the two allowlisted events `lunara_morning_desk` and `lunara_needs_attention` back to IFTTT. Add it only to deployment configuration:

```php
define( 'LUNARA_IFTTT_WEBHOOK_KEY', 'PASTE_THE_PRIVATE_WEBHOOKS_KEY_HERE' );
```

Never commit either credential, store the Webhooks key in a WordPress option, place the operator token in a URL, or paste either value into an issue or chat.

## Shared Webhooks action settings

For every IFTTT **Webhooks → Make a web request** action that calls Lunara, use:

- Method: `POST`
- Content type: `application/json`
- Additional headers:

```text
Authorization: Bearer PASTE_THE_IFTTT_OPERATOR_TOKEN_HERE
```

IFTTT supports one header per line. Use the Applet composer's **Add ingredient** control for dynamic values. In the examples below, `{{Ingredient}}` names an ingredient chip; do not type the braces as ordinary text. Wrap text ingredients in `<<< >>>` so quotes and line breaks cannot break JSON.

## Endpoint map

```text
POST https://lunarafilm.com/wp-json/lunara/v1/journal/automation/capture
POST https://lunarafilm.com/wp-json/lunara/v1/journal/automation/run-dispatch
POST https://lunarafilm.com/wp-json/lunara/v1/journal/automation/morning-desk
```

The Control Desk also displays these URLs after deployment. Keep the token in the header, never in a query string.

## Seven Applets, six workflows

Morning Desk is a two-way workflow and therefore uses two Applets. The other five workflows use one Applet each.

### 1. Morning Desk request

- If This: **Date & Time → Every day at** (recommended starting time: 7:30 AM Central).
- Then That: **Webhooks → Make a web request**.
- URL: the `morning-desk` endpoint above.
- Body:

```json
{}
```

Foundation deduplicates this request by calendar day, compiles local Journal/Dispatch/inbox status, and queues the outbound notification without waiting on external editorial work.

### 2. Morning Desk notification

- If This: **Webhooks → Receive a web request**.
- Event name: `lunara_morning_desk`.
- Then That: **Notifications → Send a rich notification from the IFTTT app**.
- Title: `Lunara Morning Desk`
- Message: add the `Value1` ingredient.
- Link URL: add the `Value2` ingredient.

### 3. Run Lunara

- If This: **Button widget → Button press**.
- Then That: **Webhooks → Make a web request**.
- URL: the `run-dispatch` endpoint above.
- Body:

```json
{
  "event_id": "run-<<<{{OccurredAt}}>>>"
}
```

This only queues Dispatch's tested asynchronous runner. It does not wait for collection, create public posts, or publish anything.

### 4. Capture Idea

- If This: **Note widget → Any new note**.
- Then That: **Webhooks → Make a web request**.
- URL: the `capture` endpoint above.
- Body:

```json
{
  "type": "idea",
  "note": "<<<{{NoteText}}>>>",
  "submitted_at": "<<<{{OccurredAt}}>>>"
}
```

The result is one private `lunara_signal` draft in **Control Desk → Automation → Automation Inbox**. Identical IFTTT retries cannot create duplicates.

### 5. Source Radar

Start deliberately instead of importing an entire news firehose.

- Preferred If This: **Feedly → New article saved for later**.
- Alternative: **RSS Feed → New feed item matches**, limited to one source and a narrow phrase.
- Then That: **Webhooks → Make a web request**.
- URL: the `capture` endpoint above.
- Body, using the matching title/URL/date ingredient chips supplied by the chosen trigger:

```json
{
  "type": "source",
  "title": "<<<{{ArticleTitle}}>>>",
  "source_url": "<<<{{ArticleURL}}>>>",
  "submitted_at": "<<<{{CreatedAt}}>>>"
}
```

The exact source URL is required and stored with the private signal. It is not converted into an article automatically. Dispatch remains responsible for source-image discovery when a source is deliberately run through its editorial pipeline.

### 6. Screening Follow-Up

Use a dedicated **Lunara Screenings** calendar so ordinary appointments never enter the editorial inbox.

- If This: **Google Calendar → Any event ends**, selecting the Lunara Screenings calendar.
- Then That: **Webhooks → Make a web request**.
- URL: the `capture` endpoint above.
- Body:

```json
{
  "type": "screening",
  "film_title": "<<<{{Title}}>>>",
  "note": "<<<{{Description}}>>> — <<<{{Where}}>>>",
  "source_url": "<<<{{EventUrl}}>>>",
  "submitted_at": "<<<{{Ends}}>>>"
}
```

Google Calendar may fire within roughly 15 minutes of an event ending. The result remains a private inbox item, not a Journal draft.

### 7. Needs Attention

- If This: **Webhooks → Receive a web request**.
- Event name: `lunara_needs_attention`.
- Then That: **Notifications → Send a rich notification from the IFTTT app**.
- Title: `Lunara needs attention`
- Message: add the `Value1` ingredient.
- Link URL: add the `Value2` ingredient.
- High priority: enable only if Dalton wants these alerts to bypass Do Not Disturb.

This event fires only for failed Dispatch reports, failed Journal validation, and a manual connection test from Control Desk. It does not send success chatter.

## Activation gate

Connect the Applets in this order:

1. Deploy Foundation `1.2.5`, then theme `3.2.17`.
2. Open **Control Desk → Automation** and confirm Foundation and Dispatch are detected.
3. Generate the dedicated `ifttt_operator` token.
4. Add `LUNARA_IFTTT_WEBHOOK_KEY` to production deployment configuration.
5. Connect **Needs Attention**, then click **Send Connection Test** in Control Desk.
6. Connect **Capture Idea** and submit one harmless note. Confirm exactly one private inbox item appears; repeat the same IFTTT activity and confirm no duplicate.
7. Connect **Run Lunara** and verify that Control Desk reports the request as queued, not synchronous.
8. Connect the paired **Morning Desk** Applets and use **Send Morning Desk Now** before enabling the daily schedule.
9. Add **Screening Follow-Up** and **Source Radar** last, one source/calendar at a time.

## Emergency stop and rollback

The quickest stop is to disable the affected IFTTT Applet. For a complete stop:

1. Disable all seven Applets.
2. Revoke the `ifttt_operator` token in **Journal → Journal Bridge**.
3. Remove `LUNARA_IFTTT_WEBHOOK_KEY` from deployment configuration.
4. If code rollback is necessary, redeploy Foundation `1.2.4` and theme `3.2.16` from WordPress.com deployment history. Dispatch remains unchanged.

Private inbox signals and bounded audit history can remain in WordPress through rollback; they have no public routes.

## Official IFTTT references

- Webhooks action fields and additional headers: https://ifttt.com/maker_webhooks/actions/make_web_request
- Webhooks event values: https://help.ifttt.com/hc/en-us/articles/115010230347-Webhooks-service-FAQ
- JSON escaping and outbound timeout: https://help.ifttt.com/hc/en-us/articles/1260803042229-Troubleshooting-outbound-webhooks
- Multi-action Applets: https://help.ifttt.com/hc/en-us/articles/4410084170651-How-to-create-multi-action-Applets
