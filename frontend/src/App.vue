<template>
  <div class="app">
    <header class="header">
      <h1>🏊 Kalendarz Zajęć Pływania</h1>
      <p>Zaplanuj i przeglądaj swoje zajęcia pływackie</p>
    </header>
    
    <div class="calendar-container">
      <div class="calendar-header">
        <div class="nav-buttons">
          <button class="nav-btn" @click="previousMonth">&lt; Poprzedni</button>
          <button class="nav-btn" @click="nextMonth">Następny &gt;</button>
        </div>
        <h2>{{ currentMonthName }} {{ currentYear }}</h2>
        <button class="add-event-btn" @click="openAddModal()">+ Dodaj zajęcia</button>
      </div>
      
      <div class="weekdays">
        <div class="weekday" v-for="day in weekdays" :key="day">{{ day }}</div>
      </div>
      
      <div class="days-grid">
        <div 
          v-for="(day, index) in calendarDays" 
          :key="index"
          :class="['day-cell', { 'other-month': !day.isCurrentMonth, 'today': day.isToday }]"
          @click="openAddModal(day.date)"
        >
          <div class="day-number">{{ day.dayNumber }}</div>
          <div 
            v-for="event in getEventsForDay(day.date)" 
            :key="event.id"
            class="event"
            @click.stop="openViewModal(event)"
          >
            {{ event.time ? event.time + ' ' : '' }}{{ event.title }}
          </div>
        </div>
      </div>
    </div>
    
    <!-- Add/Edit Event Modal -->
    <div v-if="showModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal">
        <h3>{{ editingEvent ? 'Edytuj zajęcia' : 'Dodaj nowe zajęcia' }}</h3>
        <form @submit.prevent="saveEvent">
          <div class="form-group">
            <label for="title">Nazwa zajęć *</label>
            <input type="text" id="title" v-model="eventForm.title" required placeholder="np. Nauka pływania dla dzieci">
          </div>
          <div class="form-group">
            <label for="date">Data *</label>
            <input type="date" id="date" v-model="eventForm.date" required>
          </div>
          <div class="form-group">
            <label for="time">Godzina</label>
            <input type="time" id="time" v-model="eventForm.time" placeholder="np. 10:00">
          </div>
          <div class="form-group">
            <label for="instructor">Instruktor</label>
            <input type="text" id="instructor" v-model="eventForm.instructor" placeholder="np. Jan Kowalski">
          </div>
          <div class="form-group">
            <label for="description">Opis</label>
            <textarea id="description" v-model="eventForm.description" placeholder="Dodatkowe informacje o zajęciach..."></textarea>
          </div>
          <div class="modal-buttons">
            <button type="button" class="btn btn-secondary" @click="closeModal">Anuluj</button>
            <button v-if="editingEvent" type="button" class="btn btn-danger" @click="deleteEvent">Usuń</button>
            <button type="submit" class="btn btn-primary">{{ editingEvent ? 'Zapisz' : 'Dodaj' }}</button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- View Event Modal -->
    <div v-if="showViewModal" class="modal-overlay" @click.self="closeViewModal">
      <div class="modal">
        <h3>{{ selectedEvent?.title }}</h3>
        <div class="event-details">
          <p><strong>Data:</strong> {{ formatDate(selectedEvent?.date) }}</p>
          <p v-if="selectedEvent?.time"><strong>Godzina:</strong> {{ selectedEvent.time }}</p>
          <p v-if="selectedEvent?.instructor"><strong>Instruktor:</strong> {{ selectedEvent.instructor }}</p>
          <p v-if="selectedEvent?.description"><strong>Opis:</strong> {{ selectedEvent.description }}</p>
        </div>
        <div class="modal-buttons">
          <button class="btn btn-secondary" @click="closeViewModal">Zamknij</button>
          <button class="btn btn-primary" @click="openEditModal">Edytuj</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const API_URL = import.meta.env.VITE_API_URL || '/api';

export default {
  name: 'App',
  data() {
    return {
      currentDate: new Date(),
      events: [],
      showModal: false,
      showViewModal: false,
      editingEvent: null,
      selectedEvent: null,
      eventForm: {
        title: '',
        date: '',
        time: '',
        instructor: '',
        description: ''
      },
      weekdays: ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'Sb', 'Nd'],
      months: [
        'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec',
        'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'
      ]
    };
  },
  computed: {
    currentYear() {
      return this.currentDate.getFullYear();
    },
    currentMonth() {
      return this.currentDate.getMonth();
    },
    currentMonthName() {
      return this.months[this.currentMonth];
    },
    calendarDays() {
      const days = [];
      const year = this.currentYear;
      const month = this.currentMonth;
      
      // First day of the month
      const firstDay = new Date(year, month, 1);
      // Last day of the month
      const lastDay = new Date(year, month + 1, 0);
      
      // Get the day of week for the first day (0 = Sunday, adjust for Monday start)
      let startDay = firstDay.getDay();
      startDay = startDay === 0 ? 6 : startDay - 1; // Convert to Monday = 0
      
      // Days from previous month
      const prevMonthLastDay = new Date(year, month, 0).getDate();
      for (let i = startDay - 1; i >= 0; i--) {
        const day = prevMonthLastDay - i;
        const date = new Date(year, month - 1, day);
        days.push({
          dayNumber: day,
          date: this.formatDateISO(date),
          isCurrentMonth: false,
          isToday: this.isToday(date)
        });
      }
      
      // Days of current month
      for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        days.push({
          dayNumber: day,
          date: this.formatDateISO(date),
          isCurrentMonth: true,
          isToday: this.isToday(date)
        });
      }
      
      // Days from next month to fill the grid
      const remainingDays = 42 - days.length; // 6 weeks * 7 days
      for (let day = 1; day <= remainingDays; day++) {
        const date = new Date(year, month + 1, day);
        days.push({
          dayNumber: day,
          date: this.formatDateISO(date),
          isCurrentMonth: false,
          isToday: this.isToday(date)
        });
      }
      
      return days;
    }
  },
  methods: {
    async fetchEvents() {
      try {
        const response = await fetch(`${API_URL}/events`);
        if (response.ok) {
          this.events = await response.json();
        }
      } catch (error) {
        console.error('Error fetching events:', error);
        // Use local storage as fallback
        const storedEvents = localStorage.getItem('calendarEvents');
        if (storedEvents) {
          this.events = JSON.parse(storedEvents);
        }
      }
    },
    getEventsForDay(date) {
      return this.events.filter(event => event.date === date);
    },
    formatDateISO(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    },
    formatDate(dateString) {
      if (!dateString) return '';
      const date = new Date(dateString);
      return date.toLocaleDateString('pl-PL', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
    },
    isToday(date) {
      const today = new Date();
      return date.getDate() === today.getDate() &&
             date.getMonth() === today.getMonth() &&
             date.getFullYear() === today.getFullYear();
    },
    previousMonth() {
      this.currentDate = new Date(this.currentYear, this.currentMonth - 1, 1);
    },
    nextMonth() {
      this.currentDate = new Date(this.currentYear, this.currentMonth + 1, 1);
    },
    openAddModal(date = null) {
      this.editingEvent = null;
      this.eventForm = {
        title: '',
        date: date || this.formatDateISO(new Date()),
        time: '',
        instructor: '',
        description: ''
      };
      this.showModal = true;
    },
    openViewModal(event) {
      this.selectedEvent = event;
      this.showViewModal = true;
    },
    closeViewModal() {
      this.showViewModal = false;
      this.selectedEvent = null;
    },
    openEditModal() {
      this.closeViewModal();
      this.editingEvent = this.selectedEvent;
      this.eventForm = { ...this.selectedEvent };
      this.showModal = true;
    },
    closeModal() {
      this.showModal = false;
      this.editingEvent = null;
    },
    async saveEvent() {
      const eventData = { ...this.eventForm };
      
      try {
        if (this.editingEvent) {
          // Update existing event
          const response = await fetch(`${API_URL}/events/${this.editingEvent.id}`, {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(eventData)
          });
          
          if (response.ok) {
            const updatedEvent = await response.json();
            const index = this.events.findIndex(e => e.id === this.editingEvent.id);
            if (index !== -1) {
              this.events[index] = updatedEvent;
            }
          } else {
            throw new Error('Failed to update event');
          }
        } else {
          // Create new event
          const response = await fetch(`${API_URL}/events`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(eventData)
          });
          
          if (response.ok) {
            const newEvent = await response.json();
            this.events.push(newEvent);
          } else {
            throw new Error('Failed to create event');
          }
        }
      } catch (error) {
        console.error('Error saving event:', error);
        // Fallback to local storage
        this.saveToLocalStorage(eventData);
      }
      
      this.closeModal();
    },
    async deleteEvent() {
      if (!this.editingEvent) return;
      
      if (!confirm('Czy na pewno chcesz usunąć te zajęcia?')) return;
      
      try {
        const response = await fetch(`${API_URL}/events/${this.editingEvent.id}`, {
          method: 'DELETE'
        });
        
        if (response.ok) {
          this.events = this.events.filter(e => e.id !== this.editingEvent.id);
        } else {
          throw new Error('Failed to delete event');
        }
      } catch (error) {
        console.error('Error deleting event:', error);
        // Fallback to local storage
        this.events = this.events.filter(e => e.id !== this.editingEvent.id);
        localStorage.setItem('calendarEvents', JSON.stringify(this.events));
      }
      
      this.closeModal();
    },
    saveToLocalStorage(eventData) {
      if (this.editingEvent) {
        const index = this.events.findIndex(e => e.id === this.editingEvent.id);
        if (index !== -1) {
          this.events[index] = { ...this.events[index], ...eventData };
        }
      } else {
        // Generate unique ID using timestamp + random number for robustness
        const ids = this.events.map(e => (typeof e.id === 'number' && e.id > 0) ? e.id : 0);
        const maxId = ids.length > 0 ? Math.max(...ids) : 0;
        const newId = maxId + 1;
        this.events.push({ ...eventData, id: newId, created_at: new Date().toISOString() });
      }
      localStorage.setItem('calendarEvents', JSON.stringify(this.events));
    }
  },
  mounted() {
    this.fetchEvents();
  }
};
</script>
