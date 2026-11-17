let incidents=[],team=[],resources=[],victims=[];

const $=id=>document.getElementById(id);
const esc=v=>String(v??"").replace(/[&<>"']/g,m=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[m]));

function api(url,options={}){
    return fetch(url,options).then(async r=>{
        const text=await r.text();
        let data;
        try{data=JSON.parse(text)}catch(e){throw new Error(text||"Server returned invalid JSON")}
        if(!r.ok||data.success===false) throw new Error(data.message||"Request failed");
        return data;
    });
}

function showPage(page){
    document.querySelectorAll(".page").forEach(x=>x.classList.remove("active"));
    document.querySelectorAll(".nav-btn").forEach(x=>x.classList.remove("active"));
    $(page).classList.add("active");
    document.querySelector(`[data-page="${page}"]`)?.classList.add("active");
    if(page==="dashboard") updateDashboard();
    if(page==="incidents") renderIncidents();
    if(page==="team") renderTeam();
    if(page==="resources") renderResources();
    if(page==="victims") renderVictims();
    if(page==="reports") loadReports();
    if(page==="settings") loadSettings();
}

document.querySelectorAll(".nav-btn[data-page]").forEach(b=>b.addEventListener("click",()=>showPage(b.dataset.page)));

$("logoutBtn").addEventListener("click",()=>{
    sessionStorage.removeItem("loggedIn");
    location.href="login.html";
});

async function loadAll(){
    try{
        [incidents,team,resources,victims]=await Promise.all([
            api("incidents.php"),api("team.php"),api("resources.php"),api("victims.php")
        ]);
        updateDashboard();renderIncidents();renderTeam();renderResources();renderVictims();
    }catch(e){console.error(e);alert("Could not load data: "+e.message)}
}

function updateDashboard(){
    const active=incidents.filter(i=>i.status==="Active").length;
    const available=team.filter(t=>t.status==="Available").length;
    const amb=resources.find(r=>r.name.toLowerCase()==="ambulance");
    $("statActive").textContent=active;
    $("statResponders").textContent=available;
    $("statAmbulance").textContent=amb?amb.quantity:0;
    $("statVictims").textContent=victims.length;

    $("recentIncidents").innerHTML=incidents.slice(0,5).map(i=>`
      <tr><td>${esc(i.id)}</td><td>${esc(i.title)}</td><td><a target="_blank" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(i.location)}">📍 ${esc(i.location)}</a></td>
      <td>${esc(i.priority)}</td><td>${statusBadge(i.status)}</td><td>${esc(i.responder||"Not Assigned")}</td></tr>`).join("")||`<tr><td colspan="6" class="empty">No incidents found.</td></tr>`;

    const high=incidents.filter(i=>["High","Critical"].includes(i.priority)&&i.status!=="Resolved").length;
    $("alertText").textContent=`${active} active emergency incident(s), including ${high} high-priority alert(s).`;
}

function statusBadge(s){
    const cls=s==="Resolved"?"resolved-b":s==="Assigned"?"assigned-b":"active-b";
    return `<span class="badge ${cls}">${esc(s)}</span>`;
}

function renderIncidents(){
    const q=($("incidentSearch")?.value||"").toLowerCase();
    const p=$("priorityFilter")?.value||"";
    const s=$("statusFilter")?.value||"";
    const rows=incidents.filter(i=>(!q||`${i.title} ${i.location}`.toLowerCase().includes(q))&&(!p||i.priority===p)&&(!s||i.status===s));
    $("incidentTableBody").innerHTML=rows.map(i=>`
    <tr>
      <td>${esc(i.id)}</td><td><b>${esc(i.title)}</b><br><small>${esc(i.description||"")}</small></td>
      <td><a target="_blank" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(i.location)}">${esc(i.location)}</a></td>
      <td class="priority-${esc(i.priority).toLowerCase()}">${esc(i.priority)}</td>
      <td>${statusBadge(i.status)}</td><td>${esc(i.responder||"Not Assigned")}</td>
      <td>${esc(i.created_at||"")}</td>
      <td class="actions">
        <button class="btn primary" onclick="editIncident(${i.id})">Edit</button>
        <button class="btn danger" onclick="deleteIncident(${i.id})">Delete</button>
      </td>
    </tr>`).join("")||`<tr><td colspan="8" class="empty">No incidents found.</td></tr>`;
}

function openIncidentModal(id=null){
    const i=incidents.find(x=>Number(x.id)===Number(id));
    $("modalTitle").textContent=i?"Update Incident":"Add Incident";
    $("modalContent").innerHTML=`
    <form onsubmit="saveIncident(event,${i?i.id:"null"})">
      <div class="form-grid">
        <div><label>Incident Title</label><input id="fTitle" required value="${esc(i?.title||"")}"></div>
        <div><label>Location</label><input id="fLocation" required value="${esc(i?.location||"")}"></div>
        <div><label>Priority</label><select id="fPriority"><option>Low</option><option>Medium</option><option>High</option><option>Critical</option></select></div>
        <div><label>Status</label><select id="fStatus"><option>Active</option><option>Assigned</option><option>Resolved</option></select></div>
        <div><label>Responder / Team</label><input id="fResponder" value="${esc(i?.responder||"")}"></div>
        <div class="full"><label>Description</label><textarea id="fDescription">${esc(i?.description||"")}</textarea></div>
      </div>
      <br><button class="btn primary" type="submit">${i?"Save Changes":"Create Incident"}</button>
    </form>`;
    if(i){$("fPriority").value=i.priority;$("fStatus").value=i.status}
    $("modal").classList.add("show");
}
function editIncident(id){openIncidentModal(id)}
async function saveIncident(e,id){
    e.preventDefault();
    const data={title:$("fTitle").value.trim(),location:$("fLocation").value.trim(),priority:$("fPriority").value,status:$("fStatus").value,responder:$("fResponder").value.trim(),description:$("fDescription").value.trim()};
    try{
        if(id){
            await api("incidents.php",{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify({id,status:data.status,responder:data.responder})});
            // Title/location/priority/description are left unchanged by PATCH.
            // If the user edits those fields, send a POST-compatible full update through a second endpoint is not available,
            // so keep the current record fields and update status/responder here.
        }else{
            await api("incidents.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(data)});
        }
        closeModal();await loadAll();
    }catch(err){alert(err.message)}
}
async function deleteIncident(id){
    if(!confirm("Delete this incident?"))return;
    try{await api(`incidents.php?id=${id}`,{method:"DELETE"});await loadAll()}catch(e){alert(e.message)}
}

function renderTeam(){
    $("teamBody").innerHTML=team.map(t=>`<tr><td><b>${esc(t.name)}</b></td><td>${esc(t.role)}</td><td>${esc(t.phone)}</td><td>${esc(t.status)}</td><td>${esc(t.assigned_incident||"None")}</td><td class="actions"><button class="btn primary" onclick="editTeam(${t.id})">Edit</button><button class="btn danger" onclick="deleteTeam(${t.id})">Delete</button></td></tr>`).join("")||`<tr><td colspan="6" class="empty">No team members.</td></tr>`;
}
function openTeamModal(id=null){
    const t=team.find(x=>Number(x.id)===Number(id));
    $("modalTitle").textContent=t?"Edit Team Member":"Add Team Member";
    $("modalContent").innerHTML=`<form onsubmit="saveTeam(event,${t?t.id:"null"})"><div class="form-grid">
    <div><label>Name</label><input id="tName" required value="${esc(t?.name||"")}"></div>
    <div><label>Role</label><input id="tRole" required value="${esc(t?.role||"")}"></div>
    <div><label>Phone</label><input id="tPhone" value="${esc(t?.phone||"")}"></div>
    <div><label>Status</label><select id="tStatus"><option>Available</option><option>On Mission</option><option>Off Duty</option></select></div>
    <div class="full"><label>Assigned Incident</label><input id="tAssigned" value="${esc(t?.assigned_incident||"")}"></div>
    </div><br><button class="btn primary">Save</button></form>`;
    if(t)$("tStatus").value=t.status;
    $("modal").classList.add("show");
}
function editTeam(id){openTeamModal(id)}
async function saveTeam(e,id){e.preventDefault();const d={id,name:$("tName").value.trim(),role:$("tRole").value.trim(),phone:$("tPhone").value.trim(),status:$("tStatus").value,assigned_incident:$("tAssigned").value.trim()};try{await api("team.php",{method:id?"PATCH":"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)});closeModal();await loadAll()}catch(x){alert(x.message)}}
async function deleteTeam(id){if(!confirm("Delete this team member?"))return;try{await api(`team.php?id=${id}`,{method:"DELETE"});await loadAll()}catch(e){alert(e.message)}}

function renderResources(){
    $("resourceGrid").innerHTML=resources.map(r=>`<div class="card"><h3>🚑 ${esc(r.name)}</h3><div class="stat-value">${esc(r.quantity)}</div><p>${esc(r.unit)}</p><div class="actions"><button class="btn danger" onclick="changeResource(${r.id},-1)">− Decrease</button><button class="btn success" onclick="changeResource(${r.id},1)">+ Increase</button></div></div>`).join("");
}
async function changeResource(id,delta){const r=resources.find(x=>Number(x.id)===Number(id));if(!r)return;const q=Math.max(0,Number(r.quantity)+delta);try{await api("resources.php",{method:"PATCH",headers:{"Content-Type":"application/json"},body:JSON.stringify({id,quantity:q})});await loadAll()}catch(e){alert(e.message)}}

function renderVictims(){
    $("victimBody").innerHTML=victims.map(v=>`<tr><td>${v.id}</td><td>${esc(v.name)}</td><td>${esc(v.age??"")}</td><td>${esc(v.condition_status)}</td><td>${esc(v.location)}</td><td>${esc(v.incident_id??"")}</td><td class="actions"><button class="btn primary" onclick="editVictim(${v.id})">Update</button><button class="btn danger" onclick="deleteVictim(${v.id})">Delete</button></td></tr>`).join("")||`<tr><td colspan="7" class="empty">No victims registered.</td></tr>`;
}
function openVictimModal(id=null){
    const v=victims.find(x=>Number(x.id)===Number(id));
    $("modalTitle").textContent=v?"Update Victim":"Register Victim";
    $("modalContent").innerHTML=`<form onsubmit="saveVictim(event,${v?v.id:"null"})"><div class="form-grid">
    <div><label>Name</label><input id="vName" required value="${esc(v?.name||"")}"></div>
    <div><label>Age</label><input id="vAge" type="number" min="0" value="${esc(v?.age||"")}"></div>
    <div><label>Condition</label><select id="vCondition"><option>Stable</option><option>Critical</option><option>Injured</option><option>Recovered</option></select></div>
    <div><label>Location</label><input id="vLocation" value="${esc(v?.location||"")}"></div>
    <div><label>Incident ID</label><input id="vIncident" type="number" value="${esc(v?.incident_id||"")}"></div>
    <div class="full"><label>Notes</label><textarea id="vNotes">${esc(v?.notes||"")}</textarea></div>
    </div><br><button class="btn primary">Save</button></form>`;
    if(v)$("vCondition").value=v.condition_status;
    $("modal").classList.add("show");
}
function editVictim(id){openVictimModal(id)}
async function saveVictim(e,id){
    e.preventDefault();
    const d={id,name:$("vName").value.trim(),age:$("vAge").value,condition_status:$("vCondition").value,location:$("vLocation").value.trim(),incident_id:$("vIncident").value,notes:$("vNotes").value.trim()};
    try{await api("victims.php",{method:id?"PATCH":"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)});closeModal();await loadAll()}catch(x){alert(x.message)}
}
async function deleteVictim(id){if(!confirm("Delete this victim record?"))return;try{await api(`victims.php?id=${id}`,{method:"DELETE"});await loadAll()}catch(e){alert(e.message)}}

async function loadReports(){
    try{
        const s=await api("reports.php?action=summary");
        $("reportTotal").textContent=s.summary.total||0;$("reportActive").textContent=s.summary.active||0;$("reportAssigned").textContent=s.summary.assigned||0;$("reportResolved").textContent=s.summary.resolved||0;
        const r=await api("reports.php?action=resolved");
        $("resolvedBody").innerHTML=r.map(x=>`<tr><td>${x.id}</td><td>${esc(x.title)}</td><td>${esc(x.location)}</td><td>${esc(x.priority)}</td><td>${esc(x.responder||"")}</td><td>${esc(x.solved_at||"")}</td></tr>`).join("")||`<tr><td colspan="6" class="empty">No resolved emergencies.</td></tr>`;
        const h=await api("reports.php?action=history");
        $("historyBody").innerHTML=h.map(x=>`<tr><td>${esc(x.created_at)}</td><td>${esc(x.module_name)}</td><td>${esc(x.action)}</td><td>${esc(x.details)}</td></tr>`).join("")||`<tr><td colspan="4" class="empty">No activity history.</td></tr>`;
    }catch(e){alert("Reports error: "+e.message)}
}

async function exportCSV(){
    try{
        const rows=await api("incidents.php");
        const headers=["ID","Title","Location","Priority","Status","Responder","Description","Created At","Solved At"];
        const csv=[headers,...rows.map(x=>[x.id,x.title,x.location,x.priority,x.status,x.responder,x.description,x.created_at,x.solved_at])].map(row=>row.map(v=>`"${String(v??"").replaceAll('"','""')}"`).join(",")).join("\n");
        const a=document.createElement("a");a.href=URL.createObjectURL(new Blob([csv],{type:"text/csv"}));a.download="emergency_incidents.csv";a.click();
    }catch(e){alert(e.message)}
}

function openLatestLocation(){
    const i=incidents[0];if(!i){alert("No incidents available.");return}
    window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(i.location)}`,"_blank");
}
function closeModal(){$("modal").classList.remove("show")}
$("modal").addEventListener("click",e=>{if(e.target.id==="modal")closeModal()});

async function loadSettings(){
    try{
        const s=await api("settings.php");
        $("dbStatus").innerHTML=`● <b>Database:</b> ${esc(s.database)}`;
        $("serverInfo").innerHTML=`<b>Server:</b> ${esc(s.server)}<br><b>PHP:</b> ${esc(s.php_version)}<br><b>Server Time:</b> ${esc(s.time)}`;
    }catch(e){$("dbStatus").innerHTML=`● <b>Database:</b> Error - ${esc(e.message)}`}
}

window.addEventListener("load",async()=>{
    await loadAll();
    if(!sessionStorage.getItem("loggedIn")) {
        // Login is optional during development. Uncomment the next line to force login:
        // location.href="login.html";
    }
});
