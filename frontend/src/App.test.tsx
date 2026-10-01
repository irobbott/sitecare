import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import App from './App'

vi.mock('./api',()=>({api:{post:vi.fn()},currentUser:vi.fn().mockRejectedValue(new Error('signed out')),login:vi.fn()}))

describe('SiteCare dashboard',()=>{
  beforeEach(()=>vi.clearAllMocks())
  it('shows the overview and the website health list',()=>{
    const client=new QueryClient({defaultOptions:{queries:{retry:false}}})
    render(<QueryClientProvider client={client}><MemoryRouter><App/></MemoryRouter></QueryClientProvider>)
    expect(screen.getByRole('heading',{name:/good morning/i})).toBeInTheDocument()
    expect(screen.getByText('Website health')).toBeInTheDocument()
    expect(screen.getAllByText('Northstar Studio').length).toBeGreaterThan(0)
  })
})
