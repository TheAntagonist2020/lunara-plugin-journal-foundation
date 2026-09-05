(function () {
  'use strict';
  const config = JSON.parse(document.getElementById('desk-config').textContent);
  const H = window.LunaraDeskState;
  const e = H.escapeHtml;
  const root = document.getElementById('journal-desk');
  const state = { view:'queue', mediaOpen:false, media:null, mediaPage:1, mediaSearch:'', desk:null, settings:null, workspace:null, draft:null, base:null, revision:'', awaitingReadback:false, candidate:null, undo:null, feedback:'', busy:'', notice:null, search:'', page:1, formDirty:false, settingsEdit:null, removedSources:[], online:navigator.onLine, poll:null };
  let selectedRange = null;
  let deskRequest = 0;
  const icons = {
    queue:'<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    dispatch:'<path d="m13 2-9 12h7l-1 8 10-13h-7l1-7Z"/>',
    voice:'<path d="M5 8v8M10 4v16M15 7v10M20 10v4"/>',
    refresh:'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6 7a7 7 0 0 1 12-1l2 6M4 12l2 6a7 7 0 0 0 12-1"/>',
    arrow:'<path d="M5 12h14m-6-6 6 6-6 6"/>',
    back:'<path d="M19 12H5m6-6-6 6 6 6"/>',
    lock:'<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4"/>',
    external:'<path d="M14 3h7v7m0-7L10 14"/><path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5"/>',
  };
  function icon(name, spin) { return '<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'+(spin?' class="spin"':'')+'>'+icons[name]+'</svg>'; }
  function clone(value) { return JSON.parse(JSON.stringify(value)); }
  function dirty() { return Boolean(state.draft && H.isDirty(state.base, state.draft)); }
  function disabled() { return state.busy ? ' disabled' : ''; }
  function date(value) {
    if (!value || /^0000/.test(value)) return '';
    const parsed = new Date(/Z$|[+-]\d\d:\d\d$/.test(value) ? value : value.replace(' ', 'T') + 'Z');
    return isNaN(parsed.getTime()) ? '' : parsed.toLocaleString(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});
  }
  function link(url, label, className) { const safe=H.safeUrl(url);return safe?'<a class="'+e(className||'')+'" href="'+e(safe)+'" target="_blank" rel="noopener noreferrer">'+e(label)+'</a>':''; }
  function safeHtml(html) {
    const template=document.createElement('template');template.innerHTML=String(html||'');
    const clean=document.createElement('div');
    const allowed={P:'p',DIV:'p',EM:'em',I:'em',STRONG:'strong',B:'strong',A:'a',BR:'br',BLOCKQUOTE:'blockquote',UL:'ul',OL:'ol',LI:'li'};
    function copy(node,parent) {
      if(node.nodeType===3){parent.appendChild(document.createTextNode(node.textContent));return;}
      if(node.nodeType!==1||['SCRIPT','STYLE','IFRAME','OBJECT','SVG','MATH','FORM','INPUT','BUTTON','IMG','VIDEO','AUDIO'].includes(node.tagName))return;
      const tag=allowed[node.tagName];const target=tag?document.createElement(tag):parent;
      if(tag==='a'){const url=H.safeUrl(node.getAttribute('href'));if(url){target.href=url;target.target='_blank';target.rel='noopener noreferrer';}}
      Array.from(node.childNodes).forEach(child=>copy(child,target));
      if(tag)parent.appendChild(target);
    }
    Array.from(template.content.childNodes).forEach(node=>copy(node,clean));return clean.innerHTML;
  }
  async function api(path, body) {
    const controller=new AbortController();const timeout=setTimeout(()=>controller.abort(),95000);
    try {
      const multipart=body instanceof FormData;
      const response=await fetch(config.apiBase+path,{method:body===undefined?'GET':'POST',credentials:'same-origin',headers:{'X-WP-Nonce':config.nonce,'Accept':'application/json',...(body===undefined||multipart?{}:{'Content-Type':'application/json'})},body:body===undefined?undefined:multipart?body:JSON.stringify(body),signal:controller.signal,cache:'no-store'});
      const contentType=response.headers.get('content-type')||'';
      if(!contentType.includes('json'))throw new Error('Your session may have expired. Reload Journal Desk to sign in again.');
      const data=await response.json();
      if(!response.ok){const error=new Error(data.message||'This action could not be completed.');error.data=data.data;error.code=data.code;throw error;}
      return data;
    } catch(error) {
      if(error.name==='AbortError')throw new Error('The request took too long. Refresh before retrying so you can check whether it completed.');
      throw error;
    } finally {clearTimeout(timeout);}
  }
  function announce(text){document.getElementById('announcements').textContent=text;}
  function notify(message,error,url){state.notice={message,error:!!error,url};announce(message);}
  function handleError(error){notify(error.message||'Something went wrong. Try again.',true);if(error.data&&error.data.validation&&state.workspace)state.workspace.validation=error.data.validation;}
  function unsaved(){return dirty()||state.formDirty;}
  function leaveAllowed(){return !unsaved()||window.confirm('Leave without saving these changes?');}
  async function navigate(view){
    if(state.busy||!leaveAllowed())return;
    state.mediaOpen=false;state.media=null;state.view=view;state.workspace=null;state.draft=null;state.candidate=null;state.undo=null;state.feedback='';state.formDirty=false;state.settingsEdit=null;state.removedSources=[];state.notice=null;
    render();window.scrollTo(0,0);
    if(view==='queue')await loadDesk();else await loadSettings();
  }
  async function loadDesk(silent){
    const request=++deskRequest;
    try{const data=await api('journal/desk?limit=12&page='+state.page+'&search='+encodeURIComponent(state.search)+'&refresh=true');if(request!==deskRequest)return;state.desk=data;if(!silent||state.view==='queue')render();schedulePoll();}
    catch(error){if(request!==deskRequest)return;handleError(error);render();}
  }
  async function loadSettings(){
    state.busy='settings-load';state.settingsEdit=null;render();
    try{state.settings=await api('journal/app/settings');state.settingsEdit=clone(state.settings);state.formDirty=false;state.removedSources=[];}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  function schedulePoll(){
    clearTimeout(state.poll);
    const dispatch=state.desk&&state.desk.dispatch;
    if(dispatch&&(dispatch.running||dispatch.manual_run_queued))state.poll=setTimeout(async()=>{
      if(document.hidden){schedulePoll();return;}
      await loadDesk(true);if(state.view==='dispatch'&&!state.formDirty&&!state.busy)render();
    },30000);
  }
  async function openDraft(id){
    if(state.busy||!leaveAllowed())return;
    state.busy='open';state.notice=null;render();
    try{const data=await api('journal/app/drafts/'+id);state.mediaOpen=false;state.media=null;state.workspace=data;state.revision=data.revision;state.awaitingReadback=false;state.base=H.fromWorkspace(data.workspace);state.draft=clone(state.base);state.view='review';state.candidate=null;state.undo=null;state.feedback='';state.formDirty=false;window.scrollTo(0,0);}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  async function loadMedia(page){
    if(state.busy)return;state.mediaPage=page||1;state.media=null;state.busy='media';render();
    try{state.media=await api('journal/app/media?page='+state.mediaPage+'&search='+encodeURIComponent(state.mediaSearch));}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  function chooseImage(image){
    state.draft=H.chooseImage(state.draft,image);state.mediaOpen=false;state.candidate=null;state.undo=null;
    notify('Image selected. Check its credit and description, then save the draft.');render();
  }
  async function uploadImage(file){
    if(!file||state.busy)return;
    if(file.size>config.maxUploadBytes){notify('Choose an image smaller than '+Math.floor(config.maxUploadBytes/1048576)+' MB.',true);render();return;}
    const body=new FormData();body.append('file',file,file.name);state.busy='upload-image';render();
    try{const result=await api('journal/app/media',body);if(!result.images||!result.images.length)throw new Error('The upload finished without a usable image. Check the media library before uploading again.');chooseImage(result.images[0]);}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  function imagePicker(){
    let html='<div class="image-picker"><label class="label" for="image-upload">Upload from your device</label><input id="image-upload" type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/avif"'+disabled()+'><p class="help">JPEG, PNG, WebP, GIF or AVIF. Up to '+Math.floor(config.maxUploadBytes/1048576)+' MB. Uploads enter the WordPress media library immediately; the article changes only when you save. Uploaded file URLs are public.</p><button class="btn quiet" data-action="media-library"'+disabled()+'>Choose from your media library</button>';
    if(state.busy==='upload-image')html+='<p role="status">Uploading and preparing your image…</p>';
    if(state.busy==='media')html+='<p role="status">Loading images…</p>';
    if(state.media){
      html+='<form id="media-search-form" class="search-form"><label class="sr-only" for="media-search">Search images</label><input id="media-search" name="search" type="search" maxlength="200" placeholder="Find an image…" value="'+e(state.mediaSearch)+'"'+disabled()+'><button class="btn quiet" type="submit"'+disabled()+'>Search</button></form>';
      html+='<div class="media-grid">'+state.media.images.map(item=>'<button class="media-choice" data-image="'+Number(item.id)+'"'+disabled()+'><img loading="lazy" src="'+e(H.safeUrl(item.thumbnail||item.url))+'" alt=""><span>'+e(item.title||'Untitled image')+'</span><small>'+Number(item.width)+' × '+Number(item.height)+'</small></button>').join('')+'</div>';
      if(!state.media.images.length)html+='<p class="small muted">No images found. Try another search or upload one.</p>';
      html+='<div class="pagination"><button class="btn quiet" data-media-page="'+(state.mediaPage-1)+'"'+(state.mediaPage<=1||state.busy?' disabled':'')+'>Previous</button><span class="small">Page '+state.mediaPage+'</span><button class="btn quiet" data-media-page="'+(state.mediaPage+1)+'"'+(state.mediaPage>=state.media.total_pages||state.busy?' disabled':'')+'>Next</button></div>';
    }
    return html+'<button class="link-btn" data-action="close-media"'+disabled()+'>Close image picker</button></div>';
  }
  function publishState(){return{dirty:dirty(),awaitingReadback:state.awaitingReadback,valid:!!(state.workspace&&state.workspace.validation&&state.workspace.validation.valid),enabled:!!(state.workspace&&state.workspace.workspace.publication&&state.workspace.workspace.publication.gpt_publish_enabled),permitted:!!(state.settings&&state.settings.publication&&state.settings.publication.can_publish),busy:!!state.busy||!state.online};}
  function saveMessage(){
    if(state.busy==='save')return 'Saving and checking your draft…';if(state.busy==='publish')return 'Publishing to LUNARA…';
    if(state.awaitingReadback)return 'Saved. Reload the saved draft to check it before publishing.';
    if(dirty())return 'Unsaved changes. Save them before publishing.';
    if(!publishState().valid)return 'Resolve the publishing checks before approving.';
    if(!publishState().enabled)return 'Publishing is disabled in Journal settings.';
    return 'Saved. Your editorial approval is still required.';
  }
  function updateActions(){
    const publish=document.getElementById('publish-button');if(publish)publish.disabled=!H.canPublish(publishState());
    const message=document.getElementById('save-message');if(message){message.textContent=saveMessage();message.classList.toggle('warn',dirty());}
    const save=document.getElementById('save-button');if(save)save.disabled=!!state.busy||!state.online||state.awaitingReadback||!dirty();
  }
  async function saveDraft(){
    if(!dirty()||state.busy||state.awaitingReadback)return;state.busy='save';render();
    try{
      const id=state.workspace.workspace.id;const saved=await api('journal/app/drafts/'+id+'/save',H.saveBody(state.draft,state.revision));
      // A failed follow-up read must not leave the editor on an already-consumed revision.
      state.revision=saved.revision;state.awaitingReadback=true;state.base=clone(state.draft);state.workspace.validation=saved.validation;state.candidate=null;state.undo=null;
      const data=await api('journal/app/drafts/'+id);state.workspace=data;state.revision=data.revision;state.awaitingReadback=false;state.base=H.fromWorkspace(data.workspace);state.draft=clone(state.base);state.candidate=null;state.undo=null;
      notify('Draft saved. '+(data.validation&&data.validation.valid?'Publishing checks passed; review the voice and facts before approving.':'Some publishing checks still need attention.'));
    }catch(error){if(error.data&&error.data.partial_save){state.awaitingReadback=true;notify(error.message,true);}else if(state.awaitingReadback)notify('Your draft was saved, but the saved copy could not be reloaded. Use Reload saved draft before publishing.',true);else handleError(error);}finally{state.busy='';render();}
  }
  async function publish(){
    if(!H.canPublish(publishState()))return;
    if(!window.confirm('Publish “'+state.draft.title+'” on LUNARA now?'))return;
    state.busy='publish';render();
    try{
      const result=await api('journal/app/drafts/'+state.workspace.workspace.id+'/publish',{expected_revision:state.revision,confirm_publish_now:true});
      if(!result.published||result.post_status!=='publish')throw new Error('Publication was not confirmed. Refresh the queue before retrying.');
      if(state.desk){state.desk.drafts=state.desk.drafts.filter(item=>item.id!==result.id);state.desk.draft_count=Math.max(0,state.desk.draft_count-1);}
      state.view='queue';state.workspace=null;state.draft=null;state.base=null;state.candidate=null;
      notify('Published: '+result.title,false,result.permalink);const successNotice=state.notice;await loadDesk();if(state.notice&&state.notice.error)state.notice={...successNotice,message:successNotice.message+'. The queue could not refresh; use Refresh to reload it.'};
    }catch(error){handleError(error);}finally{state.busy='';render();}
  }
  async function reject(){
    if(state.busy||!leaveAllowed())return;state.busy='reject';render();
    try{const id=state.workspace.workspace.id;await api('journal/app/drafts/'+id+'/reject',{expected_revision:state.revision});if(state.desk)state.desk.drafts=state.desk.drafts.filter(item=>item.id!==id);state.view='queue';state.workspace=null;state.draft=null;state.base=null;state.candidate=null;notify('Removed from the review queue. The draft is retained in WordPress.');const successNotice=state.notice;await loadDesk();if(state.notice&&state.notice.error)state.notice={...successNotice,message:successNotice.message+' The queue could not refresh; use Refresh to reload it.'};}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  async function revise(){
    if(state.busy||!state.online)return;
    const input=clone(state.draft);const draftId=state.workspace.workspace.id;state.busy='revise';state.candidate=null;render();
    try{
      const result=await api('journal/app/drafts/'+draftId+'/revise',{title:input.title,content:input.content,excerpt:input.excerpt,instructions:state.feedback||'Revise this draft using the active LUNARA Journal voice. Keep its supported news and specific angle; cut abstract trade commentary and inflated claims.',expected_revision:state.revision});
      if(!result.candidate||typeof result.candidate.content!=='string')throw new Error('The revision was incomplete. Your draft has not changed.');
      state.candidate={...result,draftId,input};notify('Revision ready to compare. Your saved draft has not changed.');
    }catch(error){handleError(error);}finally{state.busy='';render();const panel=document.getElementById('candidate');if(panel)panel.scrollIntoView({behavior:'smooth',block:'start'});}
  }
  function applyCandidate(){
    if(!H.canApplyCandidate(state.candidate,state.workspace.workspace.id,state.draft)){notify('This draft changed after the revision was requested. Request a new revision before applying it.',true);render();return;}
    state.undo=clone(state.draft);const candidate=state.candidate.candidate;state.draft={...state.draft,title:candidate.title,content:candidate.content,excerpt:candidate.excerpt,seo:candidate.seo_description||state.draft.seo};state.candidate=null;notify('Revision applied to the editor. Save when it reads right.');render();
  }
  async function rememberFeedback(){
    if(!state.feedback.trim()||state.busy)return;state.busy='remember';render();
    try{
      const settings=await api('journal/app/settings');const previous=settings.voice.current_refinement||'';
      state.settings=await api('journal/app/settings',{expected_version_id:settings.version_id,voice:{current_refinement:(previous.trim()?previous.trim()+'\n\n':'')+state.feedback.trim()}});
      notify('Saved as a standing instruction for Dispatch and future revisions.');
    }catch(error){handleError(error);}finally{state.busy='';render();}
  }
  async function runDispatch(){
    if(state.busy)return;if(state.formDirty){notify('Save your source changes before running Dispatch.',true);render();return;}
    state.busy='dispatch';render();
    try{await api('journal/desk/run-dispatch',{});notify('Dispatch is queued. This desk will check for new drafts while it is open.');await loadDesk(true);}
    catch(error){handleError(error);}finally{state.busy='';render();schedulePoll();}
  }
  async function saveSettings(kind){
    if(state.busy||!state.settingsEdit)return;state.busy='settings';render();
    const body={expected_version_id:state.settings.version_id};
    if(kind==='voice')body.voice=state.settingsEdit.voice;
    if(kind==='dispatch'){body.sources=state.settingsEdit.sources;body.removed_source_ids=state.removedSources;body.selection=state.settingsEdit.selection;}
    try{state.settings=await api('journal/app/settings',body);state.settingsEdit=clone(state.settings);state.formDirty=false;state.removedSources=[];notify(kind==='voice'?'Voice saved. Dispatch and revisions now use these instructions.':'Sources and story selection saved.');}
    catch(error){handleError(error);}finally{state.busy='';render();}
  }
  function header(title,eyebrow,description,actions){return '<div class="page-heading"><div><p class="eyebrow">'+e(eyebrow)+'</p><h1>'+e(title)+'</h1>'+(description?'<p class="muted">'+e(description)+'</p>':'')+'</div>'+(actions?'<div class="actions">'+actions+'</div>':'')+'</div>';}
  function notice(){return state.notice?'<div class="notice'+(state.notice.error?' error':'')+'" role="'+(state.notice.error?'alert':'status')+'"><div>'+e(state.notice.message)+(state.notice.url?link(state.notice.url,'View on LUNARA'):'')+'</div><button data-action="dismiss" aria-label="Dismiss message">×</button></div>':'';}
  function queueView(){
    const refresh='<button class="btn quiet" data-action="refresh"'+disabled()+'>'+icon('refresh',state.busy==='open')+'Refresh</button>';
    let html=header('Draft queue','THE JOURNAL','Your next story starts here.',refresh);
    if(!state.desk)return html+'<div class="empty"><p>'+ (state.notice?'The queue could not be loaded. Use Refresh to try again.':'Loading your Journal drafts…')+'</p></div>';
    const d=state.desk.dispatch||{};const last=d.last_run||{};
    html+='<div class="status-strip"><strong>'+(d.running?'Dispatch is drafting':d.manual_run_queued?'Dispatch is queued':d.enabled?'Dispatch is active':'Dispatch is paused')+'</strong><span>'+(last.timestamp_gmt?'Last run '+e(date(last.timestamp_gmt)):'No completed run recorded')+'</span><button class="link-btn" data-view="dispatch">Open Dispatch</button></div>';
    html+='<div class="queue-tools"><form id="search-form" class="search-form"><label for="draft-search" class="sr-only">Search drafts</label><input id="draft-search" name="search" type="search" placeholder="Find a draft…" value="'+e(state.search)+'"><button class="btn quiet" type="submit">Search</button></form><span class="muted small">'+e(state.desk.draft_count)+' stored drafts</span></div>';
    const drafts=H.visibleDrafts(state.desk.drafts);
    if(!drafts.length)html+='<div class="empty"><h2>No drafts on this page</h2><p class="muted">'+(state.search?'Try a different search.':'Dispatch can find the next story worth covering.')+'</p><button class="btn" data-action="run-dispatch"'+disabled()+'>Run Dispatch</button></div>';
    else html+='<div class="draft-list">'+drafts.map(item=>'<button class="draft-row" data-draft="'+Number(item.id)+'"'+disabled()+'><span><span class="eyebrow">'+e(item.section||'Journal')+'</span><span class="draft-title">'+e(item.title||'Untitled draft')+'</span><span class="draft-meta"><span class="badge '+(item.needs_attention?'attention':'good')+'">'+(item.needs_attention?'Needs checks':'Awaiting your review')+'</span><span>'+Number(item.source_count||0)+' source'+(item.source_count===1?'':'s')+'</span><span>'+e(date(item.modified_gmt))+'</span></span></span><span class="draft-arrow">'+icon('arrow')+'</span></button>').join('')+'</div>';
    const p=state.desk.pagination||{};
    if(p.total_pages>1)html+='<div class="pagination"><button class="btn quiet" data-page="'+Math.max(1,state.page-1)+'"'+(state.page<=1?' disabled':'')+'>Previous</button><span class="muted small">Page '+state.page+' of '+Number(p.total_pages)+'</span><button class="btn quiet" data-page="'+(state.page+1)+'"'+(!p.has_more?' disabled':'')+'>Next</button></div>';
    return html;
  }
  function validationHtml(){
    const v=state.workspace.validation||{};return '<h3>Publishing checks</h3><span class="badge '+(v.valid?'good':'attention')+'">'+(v.valid?'Checks passed':'Needs attention')+'</span><p class="help">These check required fields and formatting. Your review decides whether the story is ready.</p>'+(v.errors&&v.errors.length?'<ul class="check-list errors">'+v.errors.map(x=>'<li>'+e(x)+'</li>').join('')+'</ul>':'')+(v.warnings&&v.warnings.length?'<ul class="check-list">'+v.warnings.map(x=>'<li>'+e(x)+'</li>').join('')+'</ul>':'');
  }
  function reviewView(){
    const w=state.workspace.workspace;const draft=state.draft;const acf=w.acf||{};
    const sources=Array.isArray(acf.journal_source_items)?acf.journal_source_items:[];
    const flags=H.voiceFlags(draft.content,draft.title,state.settings&&state.settings.voice.banned_phrases);
    const image=H.safeUrl(draft.imageUrl);
    let html='<div class="actions back"><button class="btn quiet" data-view="queue"'+disabled()+'>'+icon('back')+'Draft queue</button><button class="btn quiet" data-draft="'+Number(w.id)+'"'+disabled()+'>'+icon('refresh')+'Reload saved draft</button></div>';
    html+='<div class="review-grid"><div><div class="editor-card"><div class="field-group"><label class="label" for="draft-title">Headline</label><textarea id="draft-title" class="headline-field" data-field="title"'+disabled()+'>'+e(draft.title)+'</textarea></div>';
    html+='<div class="field-group"><label class="label" id="article-label">Article</label><div class="editor-toolbar" role="toolbar" aria-label="Article formatting"><button data-format="em" aria-label="Italicize selected words"><em>I</em></button><button data-format="a" aria-label="Link selected words">Link</button><span class="help">Select words to format</span></div><div id="article-editor" class="article-editor" contenteditable="'+(!state.busy)+'" role="textbox" aria-multiline="true" aria-labelledby="article-label" data-placeholder="Write the story…" spellcheck="true">'+safeHtml(draft.content)+'</div></div>';
    html+='<div class="field-group"><label class="label" for="draft-excerpt">Short summary</label><textarea id="draft-excerpt" data-field="excerpt"'+disabled()+'>'+e(draft.excerpt)+'</textarea></div><details><summary>Deck and search description</summary><div class="field-group"><label class="label" for="draft-deck">Deck</label><textarea id="draft-deck" data-field="deck"'+disabled()+'>'+e(draft.deck)+'</textarea></div><div class="field-group"><label class="label" for="draft-seo">Search description</label><textarea id="draft-seo" data-field="seo"'+disabled()+'>'+e(draft.seo)+'</textarea></div></details>';
    html+='<div class="rewrite-panel"><p class="eyebrow">VOICE & REVISION</p><h2>Find the right words</h2><label for="feedback" class="small muted">What should change in this draft?</label><textarea id="feedback" placeholder="E.g. Open with the casting news. Cut the financing jargon and the exaggerated headline."'+disabled()+'>'+e(state.feedback)+'</textarea><div class="actions"><button class="btn" data-action="revise"'+disabled()+'>'+ (state.busy==='revise'?icon('refresh',true)+'Revising…':icon('voice')+'Propose a revision')+'</button><button class="link-btn small" id="remember-feedback" data-action="remember"'+(!state.feedback.trim()||state.busy?' disabled':'')+'>Keep feedback as a standing instruction</button></div><p class="help">A revision is a proposal. It is saved only when you apply it and save the draft.</p></div></div>';
    if(state.candidate){const c=state.candidate.candidate;html+='<section class="candidate" id="candidate"><div class="candidate-head"><h2>Proposed revision</h2><span class="badge">Not saved</span></div><details><summary>Compare with the current draft</summary><h3>'+e(state.candidate.input.title)+'</h3><div class="reading-copy">'+safeHtml(state.candidate.input.content)+'</div></details><h3>'+e(c.title)+'</h3><div class="reading-copy">'+safeHtml(c.content)+'</div><p class="help">Summary: '+e(c.excerpt)+'</p>'+(state.candidate.notes?'<p class="help">'+e(Array.isArray(state.candidate.notes)?state.candidate.notes.join(' '):state.candidate.notes)+'</p>':'')+'<div class="actions"><button class="btn primary" data-action="apply-candidate">Use this revision</button><button class="btn quiet" data-action="discard-candidate">Keep current draft</button></div></section>';}
    if(state.undo)html+='<p class="help"><button class="link-btn" data-action="undo">Undo applied revision</button></p>';
    html+='</div><aside class="review-aside"><div class="card">'+validationHtml()+'</div><div class="card"><h3>Voice review</h3><p class="help">Prompts for your judgment; these are not an approval score.</p>'+(flags.length?'<ul class="check-list">'+flags.map(x=>'<li>'+e(x)+'</li>').join('')+'</ul>':'<p class="small muted">Read for a specific angle, natural voice, and claims the sources support.</p>')+'</div><div class="card"><h3>Sources</h3>'+(sources.length?'<ul class="source-list">'+sources.map(source=>'<li>'+link(source.source_url||source.url,source.source_headline||source.source_title||source.title||source.source_publication||source.source_name||source.name||'Open source')+'<p>'+e(source.source_publication||source.source_name||source.name||'')+'</p>'+(source.source_excerpt?'<details><summary>Stored excerpt</summary><p>'+e(source.source_excerpt)+'</p></details>':'')+'</li>').join('')+'</ul>':'<p class="small muted">No source links are attached.</p>')+'</div><div class="card"><h3>Featured image</h3>'+(image?'<img class="article-image" src="'+e(image)+'" alt="'+e(draft.imageAlt||'')+'" referrerpolicy="no-referrer">':'<p class="small muted">No featured image is attached.</p>')+'<button class="btn quiet" data-action="change-image" aria-expanded="'+state.mediaOpen+'"'+disabled()+'>Change image</button>'+(state.mediaOpen?imagePicker():'')+'<div class="field-group"><label class="label" for="image-credit">Image credit</label><input id="image-credit" data-field="imageCredit" value="'+e(draft.imageCredit)+'"'+disabled()+'></div><div class="field-group"><label class="label" for="image-alt">Alt text</label><textarea id="image-alt" data-field="imageAlt" maxlength="2000"'+disabled()+'>'+e(draft.imageAlt)+'</textarea><p class="help">Describe what is visible. Saving updates this image’s alt text in the media library, including other places using the same image.</p></div></div></aside></div>';
    html+='<div class="review-footer"><div><p id="save-message" class="save-state'+(dirty()?' warn':'')+'">'+e(saveMessage())+'</p>'+(!publishState().enabled?link(config.settingsUrl,'Open publishing settings','small'):'')+'</div><div class="actions"><button id="save-button" class="btn" data-action="save"'+(!dirty()||state.busy?' disabled':'')+'>Save draft</button><button id="publish-button" class="btn primary" data-action="publish"'+(!H.canPublish(publishState())?' disabled':'')+'>Approve & Publish</button><button class="btn quiet danger reject" data-action="reject"'+disabled()+'>Reject story</button></div></div>';
    return html;
  }
  function voiceView(){
    let html=header('The Journal voice','EDITORIAL DIRECTION','The same instructions guide Dispatch and every proposed revision.');
    if(!state.settingsEdit)return html+'<p class="muted">Loading voice instructions…</p>';
    const v=state.settingsEdit.voice;
    html+='<div class="settings-layout"><form id="voice-form" class="settings-form"><section class="card"><div class="field-group"><label class="label" for="voice-summary">How the Journal should sound</label><textarea class="large" id="voice-summary" data-voice="summary"'+disabled()+'>'+e(v.summary)+'</textarea></div><div class="field-group"><label class="label" for="voice-refinement">Your standing instructions</label><textarea class="large" id="voice-refinement" data-voice="current_refinement" placeholder="Corrections you want applied to future drafts…"'+disabled()+'>'+e(v.current_refinement)+'</textarea><p class="help">Use this for your latest direction. Individual draft feedback stays with that draft unless you choose to keep it here.</p></div><div class="field-group"><label class="label" for="voice-banned">Phrases to avoid</label><textarea id="voice-banned" data-voice="banned_phrases"'+disabled()+'>'+e((v.banned_phrases||[]).join('\n'))+'</textarea><p class="help">One phrase per line.</p></div></section><div class="actions"><button class="btn primary" type="submit"'+disabled()+'>Save voice instructions</button><span class="small muted">'+e(state.formDirty?'Unsaved changes':'Active version '+state.settings.config_version)+'</span></div></form><aside class="settings-aside card"><p class="eyebrow">FAN FIRST. CRITIC SECOND.</p><h2>A conversation about cinema</h2><p class="muted">Lead with the film, the person, or the reason to care. Keep the knowledge; lose the trade-paper distance.</p><p class="muted">Enthusiasm, irritation, and humor belong here when they reflect your actual take.</p><p class="help">Each save creates a version. Earlier instructions remain available in your Journal settings.</p>'+link(config.settingsUrl,'Version history','small')+'</aside></div>';
    return html;
  }
  function sourceRow(source,index){return '<div class="source-editor"><div class="source-top"><label class="check-label"><input type="checkbox" data-source="'+index+'" data-key="enabled"'+(source.enabled?' checked':'')+disabled()+'>Enabled</label><button type="button" class="link-btn small remove-source" data-remove-source="'+index+'"'+disabled()+'>Remove source</button></div><label class="label" for="source-label-'+index+'">Source name</label><input id="source-label-'+index+'" data-source="'+index+'" data-key="label" value="'+e(source.label)+'"'+disabled()+'><div class="field-group"><label class="label" for="source-url-'+index+'">Feed URL</label><input type="url" id="source-url-'+index+'" data-source="'+index+'" data-key="url" value="'+e(source.url)+'" required'+disabled()+'></div><div class="field-pair"><div><label class="label" for="source-max-'+index+'">Items per run</label><input type="number" min="1" max="50" id="source-max-'+index+'" data-source="'+index+'" data-key="max" value="'+Number(source.max||10)+'"'+disabled()+'></div><div><label class="label" for="source-priority-'+index+'">Priority</label><input type="number" min="1" max="10" id="source-priority-'+index+'" data-source="'+index+'" data-key="priority" value="'+Number(source.priority||5)+'"'+disabled()+'></div></div></div>';}
  function dispatchView(){
    let html=header('Dispatch','THE NEXT STORY','Find worthwhile news and bring it into the draft queue.','<button class="btn primary" data-action="run-dispatch"'+disabled()+'>'+icon('dispatch')+(state.busy==='dispatch'?'Queuing…':'Run Dispatch')+'</button>');
    const d=state.desk&&state.desk.dispatch||{};const last=d.last_run||{};
    html+='<div class="status-strip"><strong>'+(d.running?'Drafting now':d.manual_run_queued?'Run queued':d.enabled?'Automation active':'Automation paused')+'</strong><span>'+(d.next_run_gmt?'Next scheduled run '+e(date(d.next_run_gmt)):'No scheduled run recorded')+'</span></div>';
    if(!state.settingsEdit)return html+'<p class="muted">Loading Dispatch settings…</p>';
    const s=state.settingsEdit.selection;
    html+='<div class="settings-layout"><form id="dispatch-form" class="settings-form"><section class="card"><h2>Sources</h2><p class="help">Choose the feeds Dispatch reads. Source links stay attached to each draft.</p><div id="source-rows">'+state.settingsEdit.sources.map(sourceRow).join('')+'</div><button class="btn quiet" data-action="add-source" type="button"'+disabled()+'>Add a source</button></section><section class="card"><h2>Story selection</h2><div class="field-pair"><div class="field-group"><label class="label" for="prefer-entries">Preferred entries per run</label><input type="number" id="prefer-entries" data-selection="prefer_entries" min="1" max="3" value="'+Number(s.prefer_entries)+'"'+disabled()+'></div><div class="field-group"><label class="label" for="max-entries">Maximum entries per run</label><input type="number" id="max-entries" data-selection="max_entries" min="1" max="3" value="'+Number(s.max_entries)+'"'+disabled()+'></div></div><label class="label" for="skip-rules">When a story should be skipped</label><textarea id="skip-rules" data-selection="skip_rules"'+disabled()+'>'+e((s.skip_rules||[]).join('\n'))+'</textarea><p class="help">One instruction per line. There is no need to fill a quota.</p></section><div class="actions"><button class="btn primary" type="submit"'+disabled()+'>Save Dispatch settings</button><span class="small muted">'+(state.formDirty?'Unsaved changes':'Active version '+e(state.settings.config_version))+'</span></div></form><aside class="settings-aside"><section class="card"><h3>Last run</h3><p class="small muted">'+e(date(last.timestamp_gmt)||'Not recorded')+'</p><p>'+e(last.message||'Run Dispatch to collect the next set of stories.')+'</p><div class="run-stats"><div class="run-stat"><strong>'+Number(last.created||0)+'</strong><span>Drafts created</span></div><div class="run-stat"><strong>'+Number(last.imported||0)+'</strong><span>Source items read</span></div></div></section><section class="card"><h3>One set of instructions</h3><p class="small muted">Dispatch uses the Journal voice you set here. Every story arrives as a draft for your review.</p><button type="button" class="link-btn small" data-view="voice">Edit the Journal voice</button></section><section class="card"><h3>Schedule</h3><p class="small muted">Your existing schedule is retained.</p>'+link(config.settingsUrl,'Manage the schedule','small')+'</section></aside></div>';
    return html;
  }
  function render(){
    const active=state.view==='review'?'queue':state.view;
    const nav=[['queue','Queue'],['dispatch','Dispatch'],['voice','Voice']].map(([key,label])=>'<button type="button" role="tab" aria-selected="'+(active===key)+'" data-view="'+key+'"'+disabled()+'>'+icon(key)+'<span>'+label+'</span></button>').join('');
    root.innerHTML='<div class="shell"><aside class="sidebar"><a class="brand" href="'+e(config.deskUrl)+'" aria-label="LUNARA Journal Desk"><span class="brand-name">LUNARA</span><span class="brand-sub">FILM</span></a><nav class="nav" aria-label="Journal Desk" role="tablist">'+nav+'</nav><div class="sidebar-footer"><p>Journal Desk</p><p>'+e(config.name)+'</p>'+link(config.siteUrl,'Visit LUNARA')+'</div></aside><div class="app-body"><header class="topbar"><span class="topbar-label">JOURNAL DESK</span><span class="private-label">'+icon('lock')+'Private workspace</span></header>'+(!state.online?'<div class="offline" role="status">You’re offline. Keep this window open to retain unsaved changes.</div>':'')+'<main id="main" class="main" aria-busy="'+!!state.busy+'">'+notice()+(state.view==='review'&&state.workspace?reviewView():state.view==='voice'?voiceView():state.view==='dispatch'?dispatchView():queueView())+'</main></div></div>';
    updateActions();
  }
  root.addEventListener('click',async event=>{
    const button=event.target.closest('button');if(!button||button.disabled)return;
    if(button.dataset.view){await navigate(button.dataset.view);return;}
    if(button.dataset.image){const item=state.media&&state.media.images.find(image=>image.id===Number(button.dataset.image));if(item)chooseImage(item);return;}
    if(button.dataset.mediaPage){await loadMedia(Number(button.dataset.mediaPage));return;}
    if(button.dataset.draft){await openDraft(Number(button.dataset.draft));return;}
    if(button.dataset.page){state.page=Number(button.dataset.page);await loadDesk();return;}
    if(button.dataset.removeSource!==undefined){const i=Number(button.dataset.removeSource);const removed=state.settingsEdit.sources.splice(i,1)[0];if(removed.id)state.removedSources.push(removed.id);state.formDirty=true;render();return;}
    if(button.dataset.format){
      const editor=document.getElementById('article-editor');
      if(!selectedRange||selectedRange.collapsed||!editor.contains(selectedRange.commonAncestorContainer)){notify('Select words in the article first.',true);render();return;}
      const wrapper=document.createElement(button.dataset.format);
      if(button.dataset.format==='a'){const entered=window.prompt('Link URL (https://…)');if(entered===null)return;const url=H.safeUrl(entered);if(!url){notify('Use a complete http or https URL.',true);render();return;}wrapper.href=url;wrapper.target='_blank';wrapper.rel='noopener noreferrer';}
      try{selectedRange.surroundContents(wrapper);state.draft.content=safeHtml(editor.innerHTML);updateActions();}catch(_){notify('Select words within a single paragraph to format them.',true);render();}return;
    }
    switch(button.dataset.action){
      case 'change-image':state.mediaOpen=!state.mediaOpen;render();break;
      case 'close-media':state.mediaOpen=false;render();break;
      case 'media-library':await loadMedia(1);break;
      case 'dismiss':state.notice=null;render();break;
      case 'refresh':await loadDesk();break;
      case 'save':await saveDraft();break;
      case 'publish':await publish();break;
      case 'reject':await reject();break;
      case 'revise':await revise();break;
      case 'remember':await rememberFeedback();break;
      case 'apply-candidate':applyCandidate();break;
      case 'discard-candidate':state.candidate=null;render();break;
      case 'undo':if(state.undo){state.draft=state.undo;state.undo=null;notify('Previous draft restored in the editor.');render();}break;
      case 'run-dispatch':await runDispatch();break;
      case 'add-source':state.settingsEdit.sources.push({id:'',enabled:true,label:'',url:'',max:10,priority:5});state.formDirty=true;render();break;
    }
  });
  root.addEventListener('submit',async event=>{
    event.preventDefault();if(state.busy)return;
    if(event.target.id==='media-search-form'){state.mediaSearch=new FormData(event.target).get('search').trim();await loadMedia(1);}
    if(event.target.id==='search-form'){state.search=new FormData(event.target).get('search').trim();state.page=1;await loadDesk();}
    if(event.target.id==='voice-form')await saveSettings('voice');
    if(event.target.id==='dispatch-form')await saveSettings('dispatch');
  });
  root.addEventListener('change',async event=>{if(event.target.id==='image-upload')await uploadImage(event.target.files[0]);});
  root.addEventListener('input',event=>{
    const el=event.target;
    if(el.dataset.field){state.draft[el.dataset.field]=el.value;updateActions();}
    if(el.id==='article-editor'){state.draft.content=safeHtml(el.innerHTML);updateActions();}
    if(el.id==='feedback'){state.feedback=el.value;const button=document.getElementById('remember-feedback');if(button)button.disabled=!el.value.trim()||!!state.busy;}
    if(el.dataset.voice){state.settingsEdit.voice[el.dataset.voice]=el.dataset.voice==='banned_phrases'?el.value.split('\n').map(s=>s.trim()).filter(Boolean):el.value;state.formDirty=true;}
    if(el.dataset.source!==undefined){state.settingsEdit.sources[Number(el.dataset.source)][el.dataset.key]=el.type==='checkbox'?el.checked:el.type==='number'?Number(el.value):el.value;state.formDirty=true;}
    if(el.dataset.selection){state.settingsEdit.selection[el.dataset.selection]=el.dataset.selection==='skip_rules'?el.value.split('\n').map(s=>s.trim()).filter(Boolean):Number(el.value);state.formDirty=true;}
  });
  root.addEventListener('paste',event=>{
    if(event.target.id!=='article-editor')return;event.preventDefault();
    const selection=window.getSelection();if(!selection.rangeCount)return;const range=selection.getRangeAt(0);
    if(!event.target.contains(range.commonAncestorContainer))return;
    const fragment=document.createDocumentFragment();const lines=event.clipboardData.getData('text/plain').split(/\r?\n/);lines.forEach((line,index)=>{if(index)fragment.appendChild(document.createElement('br'));fragment.appendChild(document.createTextNode(line));});
    const end=fragment.lastChild;range.deleteContents();range.insertNode(fragment);if(end){range.setStartAfter(end);range.collapse(true);selection.removeAllRanges();selection.addRange(range);}
    state.draft.content=safeHtml(event.target.innerHTML);updateActions();
  });
  document.addEventListener('selectionchange',()=>{const selection=window.getSelection();const editor=document.getElementById('article-editor');if(editor&&selection.rangeCount&&editor.contains(selection.getRangeAt(0).commonAncestorContainer))selectedRange=selection.getRangeAt(0).cloneRange();});
  window.addEventListener('beforeunload',event=>{if(unsaved()||state.busy){event.preventDefault();event.returnValue='';}});
  window.addEventListener('online',()=>{state.online=true;render();});window.addEventListener('offline',()=>{state.online=false;render();});
  render();
  Promise.all([loadDesk(),api('journal/app/settings').then(settings=>{state.settings=settings;state.settingsEdit=clone(settings);})]).then(()=>render()).catch(error=>{handleError(error);render();});
})();
