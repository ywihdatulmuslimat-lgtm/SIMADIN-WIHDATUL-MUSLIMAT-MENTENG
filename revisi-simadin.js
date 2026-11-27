(() => {
  const removedPages = new Set([
    'kelas',
    'wali',
    'anak_asuh',
    'lansia',
    'pengguna',
    'riwayat_kelas',
    'riwayat_mengajar',
    'izin'
  ]);
  const legalCategories = [
    'Izin Operasional (IZOP)',
    'Akta Pendirian',
    'SK Pendirian Lembaga',
    'Nomor Statistik Lembaga',
    'NPWP Lembaga',
    'Sertifikat atau Dokumen Legalitas Lainnya'
  ];
  const eventCategories = [
    'Kegiatan Belajar Mengajar',
    'Keagamaan',
    'Peringatan Hari Besar Islam',
    'Rapat dan Pertemuan',
    'Perlombaan',
    'Rihlah',
    'Kegiatan Sosial',
    'Kegiatan Lainnya'
  ];
  const eventStatuses = ['Direncanakan', 'Berjalan', 'Selesai', 'Dibatalkan'];
  const state = {
    studentClass: '',
    studentYear: '',
    studentStatus: '',
    studentSearch: '',
    calendarView: null,
    legalSearch: '',
    legalCategory: '',
    legalForm: null,
    legalView: null,
    eventSearch: '',
    eventCategory: '',
    eventYear: '',
    eventForm: null,
    eventView: null
  };
  const content = document.querySelector('#content');
  const ensureRows = key => {
    if (!Array.isArray(db[key])) db[key] = [];
    return db[key];
  };
  const esc = value => window.escapeHTML
    ? window.escapeHTML(value ?? '')
    : String(value ?? '').replace(/[&<>"']/g, character => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
  const text = value => String(value ?? '').trim();
  const idArg = value => JSON.stringify(String(value ?? ''))
    .replace(/</g, '\\u003c')
    .replace(/&/g, '&amp;')
    .replace(/"/g, '&quot;');
  const normalizeGrade = value => {
    const match = text(value).match(/(?:kelas\s*)?([1-6])(?:\D|$)/i);
    return match ? match[1] : '';
  };
  const ageOf = value => {
    if (!value) return '—';
    const birth = new Date(value);
    if (Number.isNaN(birth.getTime())) return '—';
    const now = new Date();
    let age = now.getFullYear() - birth.getFullYear();
    if (now < new Date(now.getFullYear(), birth.getMonth(), birth.getDate())) age--;
    return age >= 0 ? `${age} tahun` : '—';
  };
  const yearsFor = rows => [...new Set(rows.map(row => text(row.tahun_ajaran)).filter(Boolean))].sort().reverse();
  const fileData = file => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = () => reject(new Error('File tidak dapat dibaca.'));
    reader.readAsDataURL(file);
  });
  const validateFile = (file, accepted, maxBytes) => {
    const extension = file.name.split('.').pop().toLowerCase();
    if (!accepted[extension] || (file.type && file.type !== accepted[extension])) {
      throw new Error('Format file tidak sesuai. Pilih PDF, JPG, JPEG, atau PNG.');
    }
    if (file.size < 1 || file.size > maxBytes) {
      throw new Error(`Ukuran setiap file maksimal ${Math.round(maxBytes / 1024)} KB pada prototipe lokal.`);
    }
  };
  const storeMutation = mutate => {
    const before = JSON.stringify(db);
    try {
      mutate();
      save();
      return true;
    } catch (error) {
      db = JSON.parse(before);
      alert('Penyimpanan browser gagal atau kapasitas lokal penuh. Kurangi ukuran file lalu coba kembali.');
      return false;
    }
  };
  const addAudit = (action, entity, recordId) => {
    const rows = ensureRows('audit_lokal');
    rows.push({ action, entity, recordId, at: new Date().toISOString(), label: 'DEMO LOKAL' });
    if (rows.length > 200) rows.splice(0, rows.length - 200);
  };

  ensureRows('legalitas');
  ensureRows('kegiatan');
  ensureRows('santri');

  const legacyLegal = ensureRows('izin');
  const legalRows = ensureRows('legalitas');
  legacyLegal.forEach(old => {
    if (legalRows.some(row => row.legacySourceId === old.id)) return;
    legalRows.push({
      id: crypto.randomUUID(),
      legacySourceId: old.id,
      nama: old.nama || 'Dokumen legalitas lama',
      nomor: old.nomor || '',
      jenis: old.jenis || 'Sertifikat / Dokumen Legalitas Lainnya',
      instansi: old.instansi || '',
      tanggal_terbit: old.tanggal_terbit || '',
      tanggal_berlaku: old.masa_berlaku || '',
      tanggal_kedaluwarsa: '',
      keterangan: old.catatan || '',
      fileName: '',
      fileData: '',
      mime: '',
      legacy: true
    });
  });
  if (legacyLegal.length) save();

  Object.keys(configs).forEach(key => {
    if (removedPages.has(key)) delete configs[key];
  });
  configs.legalitas = {
    title: 'Legalitas dan Perizinan',
    singular: 'Dokumen Legalitas',
    fields: []
  };
  configs.kegiatan.title = 'Kegiatan Madrasah';
  configs.kegiatan.singular = 'Kegiatan';

  document.querySelectorAll('[data-page]').forEach(button => {
    if (removedPages.has(button.dataset.page)) button.remove();
    if (button.dataset.page === 'kegiatan') button.innerHTML = '<span class="ico">⚑</span>Kegiatan Madrasah';
  });
  document.querySelectorAll('button').forEach(button => {
    if (button.textContent.trim() === 'Legalitas & Izin') button.textContent = 'Legalitas dan Perizinan';
  });
  const primaryNav = document.querySelector('.nav');
  if (primaryNav && !primaryNav.querySelector('[data-page="legalitas"]')) {
    const button = document.createElement('button');
    button.dataset.page = 'legalitas';
    button.innerHTML = '<span class="ico">▣</span>Legalitas dan Perizinan';
    button.addEventListener('click', () => window.go('legalitas'));
    primaryNav.append(button);
  }

  const extraStyle = document.createElement('style');
  extraStyle.textContent = `
    .class-tabs{display:flex;gap:7px;flex-wrap:wrap;margin:12px 0}
    .class-tabs .btn.active{background:#087b49;color:white;border-color:#087b49}
    .demo-table td{vertical-align:middle}
    .demo-form{margin-top:16px}
    .demo-form[hidden]{display:none}
    .demo-gallery{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:12px;margin:12px 0}
    .demo-gallery figure{margin:0;border:1px solid var(--line);border-radius:11px;overflow:hidden;background:#fff}
    .demo-gallery img{width:100%;height:145px;object-fit:cover;display:block}
    .demo-gallery figcaption{padding:9px;font-size:11px;overflow-wrap:anywhere}
    .demo-gallery iframe{width:100%;height:180px;border:0;background:#f4f7f4}
    .person-cell{display:flex;align-items:center;gap:9px;min-width:170px}
    .person-photo{width:48px;height:48px;flex:0 0 48px;object-fit:cover;border-radius:50%;background:#eaf6ed;cursor:pointer}
    .person-initial{display:grid;place-items:center;color:#146b3a;font-weight:800}
    .row-actions{display:flex;gap:5px}
    .demo-notice{margin:12px 0;padding:11px 13px;border-radius:10px;background:#fff8e8;border:1px solid #f3e4bd;color:#795d22;font-size:12px}
    .legal-preview{display:block;width:min(100%,760px);height:480px;border:1px solid var(--line);border-radius:10px;background:#f6f8f6}
    .legal-image-preview{max-width:100%;max-height:480px;border-radius:10px}
    .photo-preview-list{display:flex;gap:8px;flex-wrap:wrap;margin-top:8px}
    .photo-preview-list img{width:78px;height:78px;object-fit:cover;border-radius:9px;border:1px solid var(--line)}
    @media(max-width:650px){.demo-table .row-actions{flex-direction:column}.legal-preview{height:320px}}
  `;
  document.head.append(extraStyle);

  window.openEventDetail = id => { state.eventView = String(id); state.eventForm = null; window.go('kegiatan'); };

  const originalGo = window.go;
  const originalRender = window.render;
  window.go = page => {
    if (page !== 'kalender') state.calendarView = null;
    if (page === 'izin') {
      originalGo('legalitas');
      return;
    }
    if (removedPages.has(page)) {
      originalGo('dashboard');
      return;
    }
    originalGo(page);
  };
  window.render = () => {
    if (current === 'santri') return renderStudents();
    if (current === 'legalitas') return renderLegalities();
    if (current === 'kegiatan') return renderEvents();
    const result = originalRender();
    if (current === 'dashboard') {
      const activityPanel = [...content.querySelectorAll('.panel')].find(panel =>
        [...panel.querySelectorAll('.panelhead h3')].some(heading => ['Aktivitas Terbaru', 'Aktivitas Madrasah'].includes(heading.textContent.trim()))
      );
      if (activityPanel) {
        activityPanel.setAttribute('aria-label', 'Aktivitas Madrasah');
        const activityHeading = activityPanel.querySelector('.panelhead h3');
        if (activityHeading) activityHeading.textContent = 'Aktivitas Madrasah';
        activityPanel.querySelector(':scope > .actions')?.remove();

        const dashboardRows = [
          ...ensureRows('kalender').map(row => ({ row, type: 'kalender' })),
          ...ensureRows('kegiatan').map(row => ({ row, type: 'kegiatan' }))
        ].filter(item => item.row.tanggal)
          .sort((a, b) => String(b.row.tanggal).localeCompare(String(a.row.tanggal)))
          .slice(0, 6);
        const openActivity = item => {
          if (item.type === 'kegiatan') {
            state.eventView = item.row.id;
            state.eventForm = null;
            window.go('kegiatan');
            return;
          }
          state.calendarView = item.row;
          window.go('kalender');
        };
        activityPanel.querySelectorAll('.quick').forEach((card, index) => {
          const item = dashboardRows[index];
          if (!item) return;
          card.setAttribute('role', 'link');
          card.setAttribute('tabindex', '0');
          card.setAttribute('aria-label', `Detail aktivitas: ${item.row.nama || 'Agenda'}`);
          card.style.cursor = 'pointer';
          card.addEventListener('click', () => openActivity(item));
          card.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              openActivity(item);
            }
          });
        });
        activityPanel.querySelector('.empty')?.replaceChildren('Belum ada aktivitas tercatat.');
      }
    }
    if (current === 'kalender' && state.calendarView) {
      const detail = document.createElement('section');
      detail.className = 'panel';
      detail.innerHTML = `<div class="toolbar"><div><h3>${esc(state.calendarView.nama || 'Detail Agenda')}</h3><div class="muted small">${esc(state.calendarView.tanggal || '—')} · ${esc(state.calendarView.waktu || 'Waktu belum diisi')}</div></div><button class="btn" data-calendar-detail-close="true">Kembali ke kalender</button></div><div class="profile-grid"><div class="profile-item"><small>Kategori</small><b>${esc(state.calendarView.kategori || '—')}</b></div><div class="profile-item"><small>Keterangan</small><b>${esc(state.calendarView.keterangan || '—')}</b></div></div>`;
      content.prepend(detail);
      detail.querySelector('[data-calendar-detail-close]').addEventListener('click', () => {
        state.calendarView = null;
        window.render();
      });
    }
    document.querySelectorAll('button').forEach(button => {
      if (button.textContent.includes('Legalitas & Izin')) button.innerHTML = '<span class="ico">▣</span>Legalitas dan Perizinan';
    });
    document.querySelectorAll('a[href*="pengguna"], button[onclick*="pengguna"]').forEach(link => link.remove());
    return result;
  };

  const originalOpenForm = window.openForm;
  window.openForm = (key, id = null) => {
    originalOpenForm(key, id);
    if (key !== 'santri') return;
    const form = document.querySelector('#dataForm');
    const nis = form.querySelector('[name="nis"]');
    const kelas = form.querySelector('[name="kelas"]');
    if (nis) nis.required = true;
    if (kelas) kelas.required = true;
  };
  document.addEventListener('submit', event => {
    const form = event.target;
    if (form.id !== 'dataForm' || form.dataset.key !== 'santri') return;
    const nis = text(form.querySelector('[name="nis"]')?.value);
    const duplicate = ensureRows('santri').some(row =>
      text(row.nis).toLowerCase() === nis.toLowerCase() && String(row.id) !== String(editId)
    );
    if (duplicate) {
      event.preventDefault();
      event.stopImmediatePropagation();
      alert('NIS sudah digunakan. Setiap santri harus memiliki NIS yang unik.');
    }
  }, true);

  const originalOpenProfile = window.openProfile;
  window.openProfile = (kind, id, tab) => {
    originalOpenProfile(kind, id, kind === 'guru' && tab === 'mengajar' ? 'identitas' : tab);
    if (kind === 'santri') {
      document.querySelectorAll('#profileContent h3').forEach(heading => {
        if (heading.textContent.trim() === 'Riwayat Kelas') heading.closest('.panel')?.remove();
      });
    }
    if (kind === 'guru') {
      document.querySelectorAll('.profile-tabs button').forEach(button => {
        if (button.textContent.trim() === 'Riwayat Mengajar') button.remove();
      });
    }
  };

  function studentRows() {
    return ensureRows('santri').filter(row => {
      const query = state.studentSearch.toLowerCase();
      const searchable = [
        row.nama, row.nis, row.nik, row.nama_wali, row.ayah, row.ibu, row.kelas
      ].map(text).join(' ').toLowerCase();
      return (!query || searchable.includes(query))
        && (!state.studentClass || normalizeGrade(row.kelas) === state.studentClass)
        && (!state.studentYear || text(row.tahun_ajaran) === state.studentYear)
        && (!state.studentStatus || text(row.status).toLowerCase() === state.studentStatus.toLowerCase());
    });
  }

  function renderStudents() {
    const rows = ensureRows('santri');
    const years = yearsFor(rows);
    const counts = Object.fromEntries([1, 2, 3, 4, 5, 6].map(grade => [
      grade,
      rows.filter(row =>
        (!state.studentYear || text(row.tahun_ajaran) === state.studentYear)
        && (!state.studentStatus || text(row.status).toLowerCase() === state.studentStatus.toLowerCase())
        && normalizeGrade(row.kelas) === String(grade)
      ).length
    ]));
    const filtered = studentRows();
    const parentNames = row => [row.nama_wali, row.ayah, row.ibu].map(text).filter(Boolean).join(' / ') || '—';
    const tableRows = filtered.map((row, index) => `<tr>
      <td>${index + 1}</td>
      <td><div class="person-cell">${row.foto
        ? `<img class="person-photo" src="${esc(row.foto)}" alt="Foto ${esc(row.nama)}" onclick="previewPhoto(${idArg(row.id)},'santri')">`
        : `<span class="person-photo person-initial">${esc(text(row.nama).split(/\s+/).slice(0, 2).map(part => part[0] || '').join('').toUpperCase() || '?')}</span>`}
        <b>${esc(row.nama || '—')}</b></div></td>
      <td>${esc(row.nis || '—')}</td><td>${esc(row.nik || '—')}</td><td>${esc(row.gender || '—')}</td>
      <td>${esc([row.lahir, row.tanggal_lahir].filter(Boolean).join(', ') || '—')}</td>
      <td>${esc(ageOf(row.tanggal_lahir))}</td><td>${esc(row.alamat || '—')}</td><td>${esc(parentNames(row))}</td>
      <td>${esc(row.kelas || '—')}</td><td>${esc(row.tahun_ajaran || '—')}</td>
      <td><span class="tag">${esc(row.status || 'Belum diisi')}</span></td>
      <td><div class="row-actions"><button class="btn soft" onclick="openProfile('santri',${idArg(row.id)})">Detail</button><button class="btn" onclick="editRow('santri',${idArg(row.id)})">Edit</button><button class="btn danger" onclick="delRow('santri',${idArg(row.id)})">Hapus</button></div></td>
    </tr>`).join('');
    content.innerHTML = `<section class="panel">
      <div class="toolbar"><div><h3>Data Santri</h3><div class="muted small">Jumlah total data: ${rows.length} · Ditampilkan: ${filtered.length}</div></div>
        <div class="actions"><button class="btn" onclick="window.print()">Cetak / PDF</button><button class="btn soft" data-student-export="true">Ekspor Excel / CSV</button><button class="btn primary" onclick="openForm('santri')">＋ Tambah Santri</button></div>
      </div>
      <div class="data-filters"><input id="studentSearch" class="search" aria-label="Pencarian santri" placeholder="Cari nama, NIS, NIK, orang tua/wali, atau kelas..." value="${esc(state.studentSearch)}">
        <select id="studentYear" aria-label="Filter tahun ajaran"><option value="">Semua Tahun Ajaran</option>${years.map(year => `<option value="${esc(year)}" ${state.studentYear === year ? 'selected' : ''}>${esc(year)}</option>`).join('')}</select>
        <select id="studentStatus" aria-label="Filter status santri"><option value="">Semua Status</option>${['Aktif', 'Tidak aktif', 'Lulus', 'Pindah'].map(status => `<option value="${esc(status)}" ${state.studentStatus === status ? 'selected' : ''}>${esc(status)}</option>`).join('')}</select>
      </div>
      <nav class="class-tabs" aria-label="Kelompok kelas santri">
        <button class="btn ${state.studentClass === '' ? 'active' : ''}" data-student-grade="">Semua Kelas (${rows.filter(row => (!state.studentYear || text(row.tahun_ajaran) === state.studentYear) && (!state.studentStatus || text(row.status).toLowerCase() === state.studentStatus.toLowerCase())).length})</button>
        ${[1, 2, 3, 4, 5, 6].map(grade => `<button class="btn ${state.studentClass === String(grade) ? 'active' : ''}" data-student-grade="${grade}">Kelas ${grade} (${counts[grade]})</button>`).join('')}
      </nav>
      <div class="tablewrap demo-table"><table><thead><tr><th>No.</th><th>Foto / Nama Lengkap</th><th>NIS</th><th>NIK</th><th>Jenis Kelamin</th><th>Tempat, Tanggal Lahir</th><th>Umur</th><th>Alamat</th><th>Nama Orang Tua</th><th>Kelas</th><th>Tahun Ajaran</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>${tableRows || '<tr><td class="empty" colspan="13">Belum ada data yang sesuai.</td></tr>'}</tbody></table></div>
      <div class="demo-notice">Prototipe lokal: data tersimpan di browser ini, bukan MySQL. Gunakan hanya data DEMO; jangan masukkan data pribadi nyata.</div>
    </section>`;
    content.querySelector('#studentSearch').addEventListener('input', event => {
      const position = event.target.selectionStart;
      state.studentSearch = event.target.value;
      renderStudents();
      const replacement = content.querySelector('#studentSearch');
      replacement?.focus();
      replacement?.setSelectionRange(position, position);
    });
    content.querySelector('#studentYear').addEventListener('change', event => {
      state.studentYear = event.target.value;
      renderStudents();
    });
    content.querySelector('#studentStatus').addEventListener('change', event => {
      state.studentStatus = event.target.value;
      renderStudents();
    });
    content.querySelectorAll('[data-student-grade]').forEach(button => button.addEventListener('click', () => {
      state.studentClass = button.dataset.studentGrade;
      renderStudents();
    }));
    content.querySelector('[data-student-export]')?.addEventListener('click', () => exportRows('santri', [
      ['nis', 'NIS'], ['nik', 'NIK'], ['nama', 'Nama Lengkap'], ['gender', 'Jenis Kelamin'],
      ['lahir', 'Tempat Lahir'], ['tanggal_lahir', 'Tanggal Lahir'], ['alamat', 'Alamat'],
      ['nama_wali', 'Nama Wali'], ['kelas', 'Kelas'], ['tahun_ajaran', 'Tahun Ajaran'], ['status', 'Status']
    ], filtered.map(row => ({ ...row, usia: ageOf(row.tanggal_lahir) }))));
  }

  function legalMatches() {
    return ensureRows('legalitas').filter(row => {
      const q = state.legalSearch.toLowerCase();
      const matchText = [row.nama, row.nomor, row.jenis].map(text).join(' ').toLowerCase();
      return (!q || matchText.includes(q)) && (!state.legalCategory || row.jenis === state.legalCategory);
    });
  }
  function renderLegalities() {
    const rows = ensureRows('legalitas');
    const filtered = legalMatches();
    const viewed = state.legalView ? rows.find(row => String(row.id) === String(state.legalView)) : null;
    const edited = state.legalForm === null ? null : rows.find(row => String(row.id) === String(state.legalForm));
    const izop = rows.filter(row => ['Izin Operasional (IZOP)', 'Izin Operasional'].includes(row.jenis));
    const fileActions = row => row.fileData
      ? `<a class="btn btn-small" href="${esc(row.fileData)}" target="_blank" rel="noopener">Lihat</a> <a class="btn btn-small" href="${esc(row.fileData)}" download="${esc(row.fileName || 'dokumen') }">Unduh</a>`
      : '<span class="muted">File belum dilampirkan</span>';
    const izopRows = izop.map(row => `<tr><td>${esc(row.nama)}</td><td>${esc(row.nomor || '—')}</td><td>${esc(row.tanggal_terbit || '—')}</td><td>${esc(row.tanggal_kedaluwarsa || '—')}</td><td>${fileActions(row)}</td></tr>`).join('');
    const tableRows = filtered.map(row => `<tr>
      <td>${esc(row.jenis || '—')}</td><td>${esc(row.nama || '—')}</td><td>${esc(row.nomor || '—')}</td>
      <td>${esc(row.instansi || '—')}</td><td>${esc(row.tanggal_terbit || '—')}</td>
      <td>${esc(row.tanggal_berlaku || '—')}</td><td>${esc(row.tanggal_kedaluwarsa || '—')}</td>
      <td>${fileActions(row)}</td><td class="row-actions"><button class="btn soft" data-legal-view="${esc(row.id)}">Detail</button><button class="btn" data-legal-edit="${esc(row.id)}">Edit</button><button class="btn danger" data-legal-delete="${esc(row.id)}">Hapus</button></td>
    </tr>`).join('');
    const currentType = edited?.jenis || legalCategories[0];
    const availableLegalCategories = [...new Set([...legalCategories, ...rows.map(row => row.jenis).filter(Boolean)])];
    content.innerHTML = `<section class="panel">
      <div class="toolbar"><div><h3>Dokumen IZOP</h3><div class="muted small">Izin operasional madrasah yang terdaftar: ${izop.length}</div></div><button class="btn primary" data-legal-add="true">＋ Tambah IZOP</button></div>
      <div class="tablewrap"><table><thead><tr><th>Nama Dokumen</th><th>Nomor</th><th>Tanggal Terbit</th><th>Kedaluwarsa</th><th>File</th></tr></thead><tbody>${izopRows || '<tr><td colspan="5" class="empty">Dokumen IZOP belum tersedia.</td></tr>'}</tbody></table></div>
    </section>
    ${viewed ? `<section class="panel"><div class="toolbar"><div><h3>Detail Dokumen</h3><div class="muted small">${esc(viewed.jenis)}</div></div><button class="btn" data-legal-back="true">Kembali ke daftar</button></div>
      <div class="profile-grid"><div class="profile-item"><small>Nama dokumen</small><b>${esc(viewed.nama)}</b></div><div class="profile-item"><small>Nomor dokumen</small><b>${esc(viewed.nomor || '—')}</b></div><div class="profile-item"><small>Instansi penerbit</small><b>${esc(viewed.instansi || '—')}</b></div><div class="profile-item"><small>Tanggal penerbitan</small><b>${esc(viewed.tanggal_terbit || '—')}</b></div><div class="profile-item"><small>Tanggal berlaku</small><b>${esc(viewed.tanggal_berlaku || '—')}</b></div><div class="profile-item"><small>Tanggal kedaluwarsa</small><b>${esc(viewed.tanggal_kedaluwarsa || '—')}</b></div><div class="profile-item"><small>Keterangan</small><b>${esc(viewed.keterangan || '—')}</b></div></div>
      ${viewed.fileData ? (viewed.mime === 'application/pdf' ? `<iframe class="legal-preview" sandbox="" title="Pratinjau ${esc(viewed.nama)}" src="${esc(viewed.fileData)}"></iframe>` : `<img class="legal-image-preview" src="${esc(viewed.fileData)}" alt="Pratinjau ${esc(viewed.nama)}">`) : '<div class="demo-notice">Dokumen lama belum mempunyai file yang disimpan dalam prototipe ini.</div>'}
      <div class="actions">${fileActions(viewed)}<button class="btn" data-legal-edit="${esc(viewed.id)}">Edit</button></div>
    </section>` : `<section class="panel">
      <div class="toolbar"><div><h3>Legalitas dan Perizinan</h3><div class="muted small">${filtered.length} dari ${rows.length} dokumen</div></div><div class="actions"><button class="btn" onclick="window.print()">Cetak / PDF</button><button class="btn soft" data-legal-export="true">Ekspor Excel / CSV</button><button class="btn primary" data-legal-add="true">＋ Tambah Dokumen</button></div></div>
      <div class="data-filters"><input id="legalSearch" class="search" placeholder="Cari nama atau nomor dokumen..." value="${esc(state.legalSearch)}"><select id="legalCategory"><option value="">Semua Jenis Dokumen</option>${availableLegalCategories.map(type => `<option ${state.legalCategory === type ? 'selected' : ''}>${esc(type)}</option>`).join('')}</select></div>
      <div class="tablewrap demo-table"><table><thead><tr><th>Jenis</th><th>Nama Dokumen</th><th>Nomor</th><th>Instansi Penerbit</th><th>Terbit</th><th>Berlaku</th><th>Kedaluwarsa</th><th>File</th><th>Aksi</th></tr></thead><tbody>${tableRows || '<tr><td colspan="9" class="empty">Belum ada dokumen legalitas.</td></tr>'}</tbody></table></div>
    </section>`}
    ${state.legalForm !== null ? `<section class="panel demo-form"><h3>${edited ? 'Edit' : 'Tambah'} Dokumen Legalitas</h3>
      <form id="legalForm" class="formgrid">
        <div class="field"><label>Jenis dokumen *</label><select name="jenis" required>${availableLegalCategories.map(type => `<option ${currentType === type ? 'selected' : ''}>${esc(type)}</option>`).join('')}</select></div>
        <div class="field"><label>Nama dokumen *</label><input name="nama" maxlength="150" value="${esc(edited?.nama || '')}" required></div>
        <div class="field"><label>Nomor dokumen</label><input name="nomor" maxlength="100" value="${esc(edited?.nomor || '')}"></div>
        <div class="field"><label>Instansi penerbit</label><input name="instansi" maxlength="180" value="${esc(edited?.instansi || '')}"></div>
        <div class="field"><label>Tanggal penerbitan</label><input name="tanggal_terbit" type="date" value="${esc(edited?.tanggal_terbit || '')}"></div>
        <div class="field"><label>Tanggal berlaku</label><input name="tanggal_berlaku" type="date" value="${esc(edited?.tanggal_berlaku || '')}"></div>
        <div class="field"><label>Tanggal kedaluwarsa</label><input name="tanggal_kedaluwarsa" type="date" value="${esc(edited?.tanggal_kedaluwarsa || '')}"></div>
        <div class="field full"><label>Keterangan</label><textarea name="keterangan">${esc(edited?.keterangan || '')}</textarea></div>
        <div class="field full"><label>Foto atau dokumen legalitas (PDF / JPG / JPEG / PNG)${edited?.fileData ? ' — kosongkan untuk mempertahankan file' : ' *'}</label><input name="file" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" ${edited?.fileData ? '' : 'required'}><small>Unggah foto/scan dokumen legalitas. Prototipe lokal: maksimal 500 KB per file. Gunakan dokumen DEMO saja.</small></div>
        <div class="actions field full"><button class="btn primary" type="submit">Simpan</button><button class="btn" type="button" data-legal-cancel="true">Batal</button></div>
      </form>
    </section>` : ''}
    <div class="demo-notice">Prototipe HTML menyimpan data dan file pada browser ini, bukan MySQL. Jangan masukkan dokumen resmi atau data pribadi nyata.</div>`;

    content.querySelectorAll('[data-legal-add]').forEach(button => button.addEventListener('click', () => {
      state.legalForm = '';
      state.legalView = null;
      renderLegalities();
      content.querySelector('#legalForm')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }));
    content.querySelectorAll('[data-legal-edit]').forEach(button => button.addEventListener('click', () => {
      state.legalForm = button.dataset.legalEdit;
      state.legalView = null;
      renderLegalities();
    }));
    content.querySelectorAll('[data-legal-view]').forEach(button => button.addEventListener('click', () => {
      state.legalView = button.dataset.legalView;
      state.legalForm = null;
      renderLegalities();
    }));
    content.querySelectorAll('[data-legal-delete]').forEach(button => button.addEventListener('click', () => {
      if (!confirm('Hapus dokumen ini dari penyimpanan browser?')) return;
      const row = rows.find(item => String(item.id) === button.dataset.legalDelete);
      if (!row) return;
      storeMutation(() => {
        db.legalitas = rows.filter(item => String(item.id) !== button.dataset.legalDelete);
        addAudit('Hapus dokumen', 'legalitas', row.id);
      });
      state.legalView = null;
      renderLegalities();
    }));
    content.querySelector('[data-legal-back]')?.addEventListener('click', () => {
      state.legalView = null;
      renderLegalities();
    });
    content.querySelector('[data-legal-cancel]')?.addEventListener('click', () => {
      state.legalForm = null;
      renderLegalities();
    });
    const search = content.querySelector('#legalSearch');
    search?.addEventListener('input', event => {
      const position = event.target.selectionStart;
      state.legalSearch = event.target.value;
      renderLegalities();
      const replacement = content.querySelector('#legalSearch');
      replacement?.focus();
      replacement?.setSelectionRange(position, position);
    });
    content.querySelector('#legalCategory')?.addEventListener('change', event => {
      state.legalCategory = event.target.value;
      renderLegalities();
    });
    content.querySelector('[data-legal-export]')?.addEventListener('click', () => exportRows('legalitas', [
      ['jenis', 'Jenis'], ['nama', 'Nama Dokumen'], ['nomor', 'Nomor Dokumen'],
      ['instansi', 'Instansi Penerbit'], ['tanggal_terbit', 'Tanggal Penerbitan'],
      ['tanggal_berlaku', 'Tanggal Berlaku'], ['tanggal_kedaluwarsa', 'Tanggal Kedaluwarsa'],
      ['keterangan', 'Keterangan']
    ], filtered));
    content.querySelector('#legalForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const values = Object.fromEntries(new FormData(form).entries());
      const old = edited;
      const file = form.querySelector('[name="file"]').files[0];
      let upload = null;
      try {
        if (values.tanggal_terbit && values.tanggal_kedaluwarsa && values.tanggal_kedaluwarsa < values.tanggal_terbit) {
          throw new Error('Tanggal kedaluwarsa tidak boleh sebelum tanggal penerbitan.');
        }
        if (file) {
          validateFile(file, { pdf: 'application/pdf', jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' }, 512000);
          upload = { data: await fileData(file), name: file.name, mime: file.type };
        }
        const record = {
          ...(old || {}),
          id: old?.id || crypto.randomUUID(),
          jenis: values.jenis,
          nama: text(values.nama),
          nomor: text(values.nomor),
          instansi: text(values.instansi),
          tanggal_terbit: values.tanggal_terbit,
          tanggal_berlaku: values.tanggal_berlaku,
          tanggal_kedaluwarsa: values.tanggal_kedaluwarsa,
          keterangan: text(values.keterangan),
          fileName: upload?.name || old?.fileName || '',
          fileData: upload?.data || old?.fileData || '',
          mime: upload?.mime || old?.mime || '',
          legacy: false
        };
        const ok = storeMutation(() => {
          if (old) db.legalitas = rows.map(row => row.id === old.id ? record : row);
          else db.legalitas.push(record);
          addAudit(old ? 'Ubah dokumen' : 'Tambah dokumen', 'legalitas', record.id);
        });
        if (!ok) return;
        state.legalForm = null;
        state.legalView = null;
        renderLegalities();
      } catch (error) {
        alert(error.message || 'Dokumen gagal disimpan.');
      }
    });
  }

  function eventMatches() {
    return ensureRows('kegiatan').filter(row => {
      const q = state.eventSearch.toLowerCase();
      const searchable = [row.nama, row.jenis, row.lokasi, row.penanggung_jawab].map(text).join(' ').toLowerCase();
      return (!q || searchable.includes(q))
        && (!state.eventCategory || row.jenis === state.eventCategory)
        && (!state.eventYear || String(row.tanggal || '').slice(0, 4) === state.eventYear);
    }).sort((a, b) => String(b.tanggal || '').localeCompare(String(a.tanggal || '')));
  }
  function attachmentCard(file, eventId, kind, index) {
    const isPhoto = kind === 'foto';
    return `<figure>${isPhoto
      ? `<a href="${esc(file.data)}" target="_blank" rel="noopener"><img src="${esc(file.data)}" alt="${esc(file.name)}"></a>`
      : `<iframe sandbox="" title="Pratinjau ${esc(file.name)}" src="${esc(file.data)}"></iframe>`}
      <figcaption>${esc(file.name)} <button class="btn danger" data-remove-file="${kind}" data-event="${esc(eventId)}" data-index="${index}">Hapus</button>
      <a class="btn btn-small" href="${esc(file.data)}" download="${esc(file.name || (isPhoto ? 'foto-kegiatan.jpg' : 'dokumen.pdf'))}">${isPhoto ? 'Unduh Foto' : 'Unduh Dokumen'}</a></figcaption></figure>`;
  }
  function renderEvents() {
    const rows = ensureRows('kegiatan');
    const filtered = eventMatches();
    const viewed = state.eventView ? rows.find(row => String(row.id) === String(state.eventView)) : null;
    const edited = state.eventForm === null ? null : rows.find(row => String(row.id) === String(state.eventForm));
    const years = [...new Set(rows.map(row => String(row.tanggal || '').slice(0, 4)).filter(Boolean))].sort().reverse();
    const availableEventCategories = [...new Set([...eventCategories, ...rows.map(row => row.jenis).filter(Boolean)])];
    const availableEventStatuses = [...new Set([...eventStatuses, edited?.status].filter(Boolean))];
    const list = filtered.map(row => `<tr>
      <td>${esc(row.nama || '—')}</td><td>${esc(row.jenis || 'Kegiatan Lainnya')}</td><td>${esc(row.tanggal || '—')}</td>
      <td>${esc(row.waktu || '—')}</td><td>${esc(row.lokasi || '—')}</td><td>${esc(row.penanggung_jawab || '—')}</td>
      <td><span class="tag">${esc(row.status || 'Direncanakan')}</span></td>
      <td>${(row.photos || []).length} foto · ${(row.documents || []).length} dokumen</td>
      <td class="row-actions"><button class="btn soft" data-event-view="${esc(row.id)}">Detail</button><button class="btn" data-event-edit="${esc(row.id)}">Edit</button><button class="btn danger" data-event-delete="${esc(row.id)}">Hapus</button></td>
    </tr>`).join('');
    const eventForm = state.eventForm !== null ? `<section class="panel demo-form"><h3>${edited ? 'Edit' : 'Tambah'} Kegiatan</h3>
      <form id="eventForm" class="formgrid">
        <div class="field"><label>Nama kegiatan *</label><input name="nama" maxlength="150" value="${esc(edited?.nama || '')}" required></div>
        <div class="field"><label>Kategori *</label><select name="jenis" required>${availableEventCategories.map(category => `<option ${edited?.jenis === category ? 'selected' : ''}>${esc(category)}</option>`).join('')}</select></div>
        <div class="field"><label>Tanggal *</label><input name="tanggal" type="date" value="${esc(edited?.tanggal || '')}" required></div>
        <div class="field"><label>Waktu</label><input name="waktu" type="time" value="${esc(edited?.waktu || '')}"></div>
        <div class="field"><label>Lokasi</label><input name="lokasi" maxlength="150" value="${esc(edited?.lokasi || '')}"></div>
        <div class="field"><label>Penanggung jawab</label><input name="penanggung_jawab" maxlength="150" value="${esc(edited?.penanggung_jawab || '')}"></div>
        <div class="field"><label>Status</label><select name="status">${availableEventStatuses.map(status => `<option ${(edited?.status || 'Direncanakan') === status ? 'selected' : ''}>${esc(status)}</option>`).join('')}</select></div>
        <div class="field full"><label>Deskripsi</label><textarea name="deskripsi">${esc(edited?.deskripsi || '')}</textarea></div>
        <div class="field full"><label>Foto dokumentasi (JPG / JPEG / PNG; maks. 5 file baru)</label><input id="eventPhotos" name="photos" type="file" accept="image/jpeg,image/png" multiple><div class="photo-preview-list" id="photoPreviews"></div><small>Hanya unggah dokumentasi DEMO pada prototipe lokal.</small></div>
        <div class="field full"><label>Dokumen pendukung PDF (maks. 5 file baru)</label><input name="documents" type="file" accept="application/pdf,.pdf" multiple></div>
        <div class="actions field full"><button class="btn primary" type="submit">Simpan Kegiatan</button><button class="btn" type="button" data-event-cancel="true">Batal</button></div>
      </form>
    </section>` : '';
    content.innerHTML = `${viewed ? `<section class="panel"><div class="toolbar"><div><h3>${esc(viewed.nama)}</h3><div class="muted small">${esc(viewed.jenis || 'Kegiatan Lainnya')} · ${esc(viewed.tanggal || '—')}</div></div><div class="actions"><button class="btn" data-event-back="true">Kembali</button><button class="btn" data-event-edit="${esc(viewed.id)}">Edit</button></div></div>
      <div class="profile-grid"><div class="profile-item"><small>Waktu</small><b>${esc(viewed.waktu || '—')}</b></div><div class="profile-item"><small>Lokasi</small><b>${esc(viewed.lokasi || '—')}</b></div><div class="profile-item"><small>Penanggung jawab</small><b>${esc(viewed.penanggung_jawab || '—')}</b></div><div class="profile-item"><small>Status</small><b>${esc(viewed.status || 'Direncanakan')}</b></div><div class="profile-item"><small>Deskripsi</small><b>${esc(viewed.deskripsi || '—')}</b></div></div>
      <h3 style="margin-top:16px">Galeri Dokumentasi</h3><div class="demo-gallery">${(viewed.photos || []).map((file, index) => attachmentCard(file, viewed.id, 'foto', index)).join('')}${(viewed.documents || []).map((file, index) => attachmentCard(file, viewed.id, 'dokumen', index)).join('') || ''}${!(viewed.photos || []).length && !(viewed.documents || []).length ? '<div class="empty">Belum ada dokumentasi yang diunggah.</div>' : ''}</div>
      <div class="demo-notice">Foto dan dokumen ditampilkan dari file yang dipilih pengguna, bukan dokumentasi contoh.</div>
    </section>` : `<section class="panel"><div class="toolbar"><div><h3>Kegiatan Madrasah</h3><div class="muted small">${filtered.length} dari ${rows.length} kegiatan</div></div><div class="actions"><button class="btn" onclick="window.print()">Cetak / PDF</button><button class="btn soft" data-event-export="true">Ekspor Excel / CSV</button><button class="btn primary" data-event-add="true">＋ Tambah Kegiatan</button></div></div>
      <div class="data-filters"><input class="search" id="eventSearch" placeholder="Cari nama, kategori, lokasi, penanggung jawab..." value="${esc(state.eventSearch)}"><select id="eventCategory"><option value="">Semua Kategori</option>${availableEventCategories.map(category => `<option ${state.eventCategory === category ? 'selected' : ''}>${esc(category)}</option>`).join('')}</select><select id="eventYear"><option value="">Semua Tahun</option>${years.map(year => `<option ${state.eventYear === year ? 'selected' : ''}>${esc(year)}</option>`).join('')}</select></div>
      <div class="tablewrap demo-table"><table><thead><tr><th>Nama Kegiatan</th><th>Kategori</th><th>Tanggal</th><th>Waktu</th><th>Lokasi</th><th>Penanggung Jawab</th><th>Status</th><th>Lampiran</th><th>Aksi</th></tr></thead><tbody>${list || '<tr><td colspan="9" class="empty">Belum ada kegiatan.</td></tr>'}</tbody></table></div>
    </section>`}${eventForm}<div class="demo-notice">Prototipe HTML menyimpan data pada browser ini, bukan MySQL. Unggah hanya file DEMO; jangan masukkan data pribadi atau dokumen resmi.</div>`;
    content.querySelector('[data-event-add]')?.addEventListener('click', () => {
      state.eventForm = '';
      state.eventView = null;
      renderEvents();
    });
    content.querySelectorAll('[data-event-edit]').forEach(button => button.addEventListener('click', () => {
      state.eventForm = button.dataset.eventEdit;
      state.eventView = null;
      renderEvents();
    }));
    content.querySelectorAll('[data-event-view]').forEach(button => button.addEventListener('click', () => {
      state.eventView = button.dataset.eventView;
      state.eventForm = null;
      renderEvents();
    }));
    content.querySelector('[data-event-back]')?.addEventListener('click', () => {
      state.eventView = null;
      renderEvents();
    });
    content.querySelector('[data-event-cancel]')?.addEventListener('click', () => {
      state.eventForm = null;
      renderEvents();
    });
    content.querySelectorAll('[data-event-delete]').forEach(button => button.addEventListener('click', () => {
      if (!confirm('Hapus kegiatan beserta lampirannya dari penyimpanan browser?')) return;
      const id = button.dataset.eventDelete;
      storeMutation(() => {
        db.kegiatan = rows.filter(row => String(row.id) !== id);
        addAudit('Hapus kegiatan', 'kegiatan', id);
      });
      renderEvents();
    }));
    content.querySelectorAll('[data-remove-file]').forEach(button => button.addEventListener('click', () => {
      const row = rows.find(item => String(item.id) === button.dataset.event);
      if (!row || !confirm('Hapus lampiran ini?')) return;
      const key = button.dataset.removeFile === 'foto' ? 'photos' : 'documents';
      storeMutation(() => {
        row[key].splice(Number(button.dataset.index), 1);
        addAudit('Hapus lampiran', 'kegiatan', row.id);
      });
      renderEvents();
    }));
    const bindFilter = (selector, property) => content.querySelector(selector)?.addEventListener('input', event => {
      const position = event.target.selectionStart;
      state[property] = event.target.value;
      renderEvents();
      const replacement = content.querySelector(selector);
      replacement?.focus();
      replacement?.setSelectionRange(position, position);
    });
    bindFilter('#eventSearch', 'eventSearch');
    content.querySelector('#eventCategory')?.addEventListener('change', event => {
      state.eventCategory = event.target.value;
      renderEvents();
    });
    content.querySelector('#eventYear')?.addEventListener('change', event => {
      state.eventYear = event.target.value;
      renderEvents();
    });
    content.querySelector('#eventPhotos')?.addEventListener('change', event => {
      const container = content.querySelector('#photoPreviews');
      container.replaceChildren();
      Array.from(event.target.files || []).forEach(file => {
        if (!file.type.startsWith('image/')) return;
        const image = document.createElement('img');
        image.alt = file.name;
        const url = URL.createObjectURL(file);
        image.src = url;
        image.addEventListener('load', () => URL.revokeObjectURL(url), { once: true });
        container.append(image);
      });
    });
    content.querySelector('[data-event-export]')?.addEventListener('click', () => exportRows('kegiatan', [
      ['nama', 'Nama Kegiatan'], ['jenis', 'Kategori'], ['tanggal', 'Tanggal'], ['waktu', 'Waktu'],
      ['lokasi', 'Lokasi'], ['penanggung_jawab', 'Penanggung Jawab'], ['status', 'Status'], ['deskripsi', 'Deskripsi']
    ], filtered));
    content.querySelector('#eventForm')?.addEventListener('submit', async event => {
      event.preventDefault();
      const form = event.currentTarget;
      const values = Object.fromEntries(new FormData(form).entries());
      const old = edited;
      const photos = Array.from(form.querySelector('[name="photos"]').files);
      const documents = Array.from(form.querySelector('[name="documents"]').files);
      try {
        if (photos.length > 5 || documents.length > 5) throw new Error('Maksimal 5 foto dan 5 dokumen baru per penyimpanan.');
        if (photos.reduce((sum, file) => sum + file.size, 0) + documents.reduce((sum, file) => sum + file.size, 0) > 1024 * 1024) {
          throw new Error('Total file baru maksimal 1 MB per penyimpanan pada prototipe lokal.');
        }
        const photoFiles = [];
        for (const file of photos) {
          validateFile(file, { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png' }, 512000);
          photoFiles.push({ id: crypto.randomUUID(), name: file.name, mime: file.type, data: await fileData(file) });
        }
        const documentFiles = [];
        for (const file of documents) {
          validateFile(file, { pdf: 'application/pdf' }, 512000);
          documentFiles.push({ id: crypto.randomUUID(), name: file.name, mime: file.type, data: await fileData(file) });
        }
        const record = {
          ...(old || {}),
          id: old?.id || crypto.randomUUID(),
          nama: text(values.nama),
          jenis: values.jenis,
          tanggal: values.tanggal,
          waktu: values.waktu,
          lokasi: text(values.lokasi),
          penanggung_jawab: text(values.penanggung_jawab),
          deskripsi: text(values.deskripsi),
          status: values.status,
          photos: [...(old?.photos || []), ...photoFiles],
          documents: [...(old?.documents || []), ...documentFiles]
        };
        const ok = storeMutation(() => {
          if (old) db.kegiatan = rows.map(row => row.id === old.id ? record : row);
          else db.kegiatan.push(record);
          addAudit(old ? 'Ubah kegiatan' : 'Tambah kegiatan', 'kegiatan', record.id);
          if (photoFiles.length || documentFiles.length) addAudit('Unggah lampiran', 'kegiatan', record.id);
        });
        if (!ok) return;
        state.eventForm = null;
        state.eventView = record.id;
        renderEvents();
      } catch (error) {
        alert(error.message || 'Kegiatan gagal disimpan.');
      }
    });
  }

  function exportRows(filename, columns, rows) {
    const escapeCell = value => {
      let textValue = text(value);
      if (/^[\x00-\x20]*[=+\-@]/.test(textValue)) textValue = `'${textValue}`;
      return `"${textValue.replace(/"/g, '""')}"`;
    };
    const csv = [columns.map(([, label]) => escapeCell(label)).join(';')]
      .concat(rows.map(row => columns.map(([key]) => escapeCell(row[key])).join(';')))
      .join('\r\n');
    download(new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8;' }), `${filename}_demo.csv`);
  }

  window.render();
})();
