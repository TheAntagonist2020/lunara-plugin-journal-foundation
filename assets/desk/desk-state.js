(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.LunaraDeskState = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
  'use strict';
  const fields = ['title', 'content', 'excerpt', 'seo', 'deck'];
  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  }
  function safeUrl(value) {
    try { const url = new URL(String(value)); return ['http:', 'https:'].includes(url.protocol) && !url.username && !url.password ? url.href : ''; }
    catch (_) { return ''; }
  }
  function isDirty(a, b) { return fields.some(key => String(a && a[key] || '') !== String(b && b[key] || '')); }
  function canPublish(state) { return Boolean(!state.dirty && !state.awaitingReadback && state.valid && state.enabled && state.permitted && !state.busy); }
  function canApplyCandidate(candidate, draftId, draft) { return Boolean(candidate && candidate.draftId === draftId && !isDirty(candidate.input, draft)); }
  function fromWorkspace(workspace) {
    const acf = workspace.acf || {};
    return { title:workspace.title || '', content:workspace.content || '', excerpt:workspace.excerpt || '', seo:acf.journal_seo_description || '', deck:acf.journal_deck || '' };
  }
  function saveBody(draft, revision) {
    return {expected_revision:revision, title:draft.title, content:draft.content, excerpt:draft.excerpt, acf:{journal_seo_description:draft.seo, journal_deck:draft.deck}};
  }
  function visibleDrafts(drafts) { return (drafts || []).filter(draft => draft.journal_status !== 'rejected'); }
  function voiceFlags(content, headline, banned) {
    const text = String(content || '').replace(/<[^>]*>/g, ' ');
    const flags = [];
    const phrases = [...new Set([...(banned || []), 'that matters because', 'this matters because', 'that uncertainty is the point', 'for context', 'notably'])];
    const found = phrases.filter(phrase => phrase && text.toLowerCase().includes(String(phrase).toLowerCase())).slice(0,5);
    if (found.length) flags.push('Check familiar phrasing: ' + found.map(p => '“' + p + '”').join(', ') + '.');
    if (/\b(new power play|fighting its own fan base|changes everything|game.chang|unprecedented)\b/i.test(headline || '')) flags.push('Check that the headline’s claim is supported by the reporting.');
    if (!/<a\s[^>]*href=/i.test(content || '')) flags.push('Check source attribution in the article. The source list below is kept separately.');
    return flags;
  }
  return {escapeHtml, safeUrl, isDirty, canPublish, canApplyCandidate, fromWorkspace, saveBody, visibleDrafts, voiceFlags};
});
